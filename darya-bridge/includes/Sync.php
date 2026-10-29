<?php
/**
 * همگام‌سازی کسب‌وکار: محصولات، سفارش‌ها و مشتریان (هوک‌های ووکامرس)
 * الگو: تلاش ارسال فوری → در صورت شکست، افزودن به صف (Queue)
 */

if (!defined('ABSPATH')) {
	exit;
}

class Sync {

	public static function init() {
		if (!class_exists('WooCommerce')) {
			return;
		}

		// محصولات
		add_action('woocommerce_new_product', array(__CLASS__, 'onProductSaved'), 20, 2);
		add_action('woocommerce_update_product', array(__CLASS__, 'onProductSaved'), 20, 2);

		// سفارش‌ها
		add_action('woocommerce_new_order', array(__CLASS__, 'onNewOrder'), 20, 2);
		add_action('woocommerce_order_status_changed', array(__CLASS__, 'onOrderStatusChanged'), 20, 4);

		// مشتریان
		add_action('woocommerce_created_customer', array(__CLASS__, 'onCustomerCreated'), 20, 1);
		add_action('woocommerce_customer_save_address', array(__CLASS__, 'onCustomerAddressSaved'), 20, 1);
		add_action('profile_update', array(__CLASS__, 'onProfileUpdate'), 20, 1);
	}

	/* ---------------- محصولات ---------------- */

	public static function onProductSaved($product_id, $product = null) {
		if (!Settings::instance()->get('sync_products')) {
			return;
		}
		$payload = self::productPayload($product_id, $product);
		if (!$payload) {
			return;
		}
		$response = ApiClient::instance()->request('v1/products/upsert', array('products' => array($payload)));
		if (is_wp_error($response) || !ApiClient::ok($response)) {
			Queue::add('product', $payload);
		}
	}

	/** ساخت payload محصول مطابق قرارداد API داریا */
	public static function productPayload($product, $loaded = null) {
		if (!function_exists('wc_get_product')) {
			return null;
		}
		if (is_numeric($product)) {
			$product = ($loaded instanceof WC_Product) ? $loaded : wc_get_product((int) $product);
		}
		if (!$product instanceof WC_Product) {
			return null;
		}
		$id = (int) $product->get_id();
		if (!$id) {
			return null;
		}

		$sku = (string) $product->get_sku();
		if ($sku === '') {
			$sku = 'P-' . $id;
		}

		$category = '';
		$terms = get_the_terms($id, 'product_cat');
		if (is_array($terms) && !empty($terms)) {
			$first = reset($terms);
			if ($first instanceof WP_Term) {
				$category = (string) $first->name;
			}
		}

		$stock = $product->managing_stock() ? $product->get_stock_quantity() : null;

		return array(
			'wc_product_id' => $id,
			'sku'           => $sku,
			'name'          => (string) $product->get_name(),
			'sale_price'    => (float) $product->get_price(),
			'stock_qty'     => (null === $stock) ? null : (float) $stock,
			'is_active'     => ($product->get_status() === 'publish') ? 1 : 0,
			'category'      => $category,
		);
	}

	/* ---------------- سفارش‌ها ---------------- */

	public static function onNewOrder($order_id, $order = null) {
		self::syncOrderObject($order_id, $order);
	}

	public static function onOrderStatusChanged($order_id, $from = '', $to = '', $order = null) {
		self::syncOrderObject($order_id, $order);

		// پس از رسیدن به وضعیت پرداخت‌شده، نقش‌های کاربر هم فوراً همگام شود
		if (!Settings::instance()->get('sync_roles')) {
			return;
		}
		$paid = function_exists('wc_get_is_paid_statuses') ? (array) wc_get_is_paid_statuses() : array('processing', 'completed');
		if (is_string($to) && $to !== '' && in_array($to, $paid, true)) {
			$order = ($order instanceof WC_Order) ? $order : (function_exists('wc_get_order') ? wc_get_order((int) $order_id) : null);
			if ($order instanceof WC_Order && (int) $order->get_user_id() > 0) {
				RolesSync::pushUserRoles((int) $order->get_user_id());
			}
		}
	}

	private static function syncOrderObject($order_id, $order = null) {
		if (!Settings::instance()->get('sync_orders') || !function_exists('wc_get_order')) {
			return;
		}
		$order = ($order instanceof WC_Order) ? $order : wc_get_order((int) $order_id);
		if (!$order instanceof WC_Order) {
			return;
		}
		$payload = self::orderPayload($order);
		if (!$payload) {
			return;
		}
		$response = ApiClient::instance()->request('v1/orders', array('order' => $payload));
		if (is_wp_error($response) || !ApiClient::ok($response)) {
			Queue::add('order', $payload);
		}
	}

	/** ساخت payload سفارش مطابق قرارداد API داریا (سفارش → فاکتور فروش) */
	public static function orderPayload($order) {
		if (!function_exists('wc_get_order')) {
			return null;
		}
		if (is_numeric($order)) {
			$order = wc_get_order((int) $order);
		}
		if (!$order instanceof WC_Order) {
			return null;
		}

		// مشتری (مطابق شکل customer در API.md)
		$user_id = (int) $order->get_user_id();
		$user = ($user_id > 0) ? get_userdata($user_id) : false;
		$display = $user ? $user->display_name : trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
		if ($display === '') {
			$display = (string) $order->get_billing_email();
		}
		$customer = array(
			'wp_user_id'    => $user_id > 0 ? $user_id : null,
			'username'      => $user ? $user->user_login : '',
			'email'         => (string) $order->get_billing_email(),
			'display_name'  => $display,
			'phone'         => (string) $order->get_billing_phone(),
			'address'       => trim($order->get_billing_address_1() . ' ' . $order->get_billing_city()),
			'roles'         => ($user && !empty($user->roles)) ? array_values((array) $user->roles) : array(),
		);

		// اقلام سفارش
		$items = array();
		foreach ((array) $order->get_items() as $item) {
			if (!$item instanceof WC_Order_Item_Product) {
				continue;
			}
			$pid = (int) $item->get_product_id();
			$qty = (float) $item->get_quantity();
			$lineTotal = (float) $item->get_total(); // پس از تخفیف، بدون مالیات
			$price = $qty > 0 ? round($lineTotal / $qty, 6) : 0.0;

			$sku = '';
			$product = $item->get_product();
			if ($product instanceof WC_Product) {
				$sku = (string) $product->get_sku();
			}
			if ($sku === '' && $pid > 0) {
				$sku = 'P-' . $pid;
			}

			$items[] = array(
				'wc_product_id' => $pid > 0 ? $pid : null,
				'sku'           => $sku,
				'name'          => (string) $item->get_name(),
				'qty'           => $qty,
				'price'         => $price,
				'total'         => $lineTotal,
			);
		}

		$date = $order->get_date_created();

		return array(
			'wc_order_id'    => (int) $order->get_id(),
			'number'         => (string) $order->get_order_number(),
			'status'         => (string) $order->get_status(),
			'date'           => $date ? $date->date('Y-m-d H:i:s') : current_time('mysql'),
			'payment_method' => (string) $order->get_payment_method(),
			'subtotal'       => (float) $order->get_subtotal(),
			// مبلغ تخفیف (قدر مطلق)
			'discount'       => abs((float) $order->get_total_discount()),
			'tax'            => (float) $order->get_total_tax(),
			'shipping'       => (float) $order->get_shipping_total(),
			'total'          => (float) $order->get_total(),
			'customer'       => $customer,
			'items'          => $items,
		);
	}

	/* ---------------- مشتریان ---------------- */

	public static function onCustomerCreated($customer_id, $data = null, $password_generated = null) {
		self::syncCustomer((int) $customer_id);
	}

	public static function onCustomerAddressSaved($user_id, $address = null) {
		self::syncCustomer((int) $user_id);
	}

	public static function onProfileUpdate($user_id, $old_user_data = null) {
		$u = get_userdata((int) $user_id);
		if (!$u || !in_array('customer', (array) $u->roles, true)) {
			return;
		}
		self::syncCustomer((int) $user_id);
	}

	public static function syncCustomer($user_id) {
		if (!Settings::instance()->get('sync_customers')) {
			return;
		}
		$payload = self::customerPayload((int) $user_id);
		if (!$payload) {
			return;
		}
		$response = ApiClient::instance()->request('v1/customers/upsert', array('customers' => array($payload)));
		if (is_wp_error($response) || !ApiClient::ok($response)) {
			Queue::add('customer', $payload);
		}
	}

	/** ساخت payload مشتری مطابق قرارداد API داریا */
	public static function customerPayload($user_id) {
		$u = get_userdata((int) $user_id);
		if (!$u) {
			return null;
		}
		$address1 = (string) get_user_meta($u->ID, 'billing_address_1', true);
		$city = (string) get_user_meta($u->ID, 'billing_city', true);
		$roles = array_values((array) $u->roles);

		return array(
			'wp_user_id'   => (int) $u->ID,
			'username'     => $u->user_login,
			'display_name' => $u->display_name,
			'full_name'    => $u->display_name,
			'email'        => $u->user_email,
			'phone'        => (string) get_user_meta($u->ID, 'billing_phone', true),
			'address'      => trim($address1 . ' ' . $city),
			'city'         => $city,
			'postal_code'  => (string) get_user_meta($u->ID, 'billing_postcode', true),
			'roles'        => $roles,
			'wp_roles'     => $roles,
			'source'       => 'woocommerce',
		);
	}
}

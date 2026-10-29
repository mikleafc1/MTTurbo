<?php
/**
 * سبد رهاشده + تبدیل سبد به سفارش
 * رهگیری add-to-cart و بروزرسانی سبد، علامت‌گذاری سبد رهاشده (cron ۱۵ دقیقه)
 * و اعلام تبدیل سبد به سفارش در thank-you.
 */

if (!defined('ABSPATH')) {
	exit;
}

class Carts {

	public static function init() {
		if (!class_exists('WooCommerce')) {
			return;
		}
		add_action('woocommerce_add_to_cart', array(__CLASS__, 'trackCart'), 20, 1);
		add_action('woocommerce_cart_updated', array(__CLASS__, 'trackCart'), 20);
		add_action('woocommerce_thankyou', array(__CLASS__, 'markCartConverted'), 20, 1);
	}

	/* ---------------- رهگیری سبد ---------------- */

	/**
	 * ثبت/بروزرسانی سبد جاری در جدول darya_carts
	 * شناسه سبد: کاربر مهمان = 's_' + session_id (با نام نشست dbr_*)، کاربر عضو = 'u_{user_id}'
	 */
	public static function trackCart($ignored = null) {
		if (!class_exists('WooCommerce') || !function_exists('WC') || !WC() || !WC()->cart) {
			return;
		}
		if (!Settings::instance()->get('sync_carts')) {
			return;
		}

		$count = (int) WC()->cart->get_cart_contents_count();
		if ($count === 0) {
			// سبد خالی — آخرین وضعیت محتوا حفظ می‌شود
			return;
		}

		global $wpdb;
		$user_id = get_current_user_id();

		if ($user_id > 0) {
			$cart_id = 'u_' . $user_id;
		} else {
			$sid = '';
			if (WC()->session && method_exists(WC()->session, 'get_session_id')) {
				$sid = (string) WC()->session->get_session_id();
			}
			if ($sid === '') {
				// نشست PHP اختصاصی پل داریا
				if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
					if (session_name() !== 'dbr_sid') {
						session_name('dbr_sid');
					}
					@session_start();
				}
				$sid = session_id();
			}
			if ($sid === '') {
				return; // شناسه قابل استفاده نیست
			}
			$cart_id = 's_' . $sid;
		}

		// اقلام سبد
		$items = array();
		foreach ((array) WC()->cart->get_cart() as $ci) {
			$product = isset($ci['data']) ? $ci['data'] : null;
			$items[] = array(
				'name'  => ($product instanceof WC_Product) ? (string) $product->get_name() : '',
				'qty'   => isset($ci['quantity']) ? (float) $ci['quantity'] : 0,
				'price' => ($product instanceof WC_Product) ? (float) $product->get_price() : 0.0,
			);
		}

		$name = '';
		$phone = '';
		$email = '';
		$roles = '';
		if ($user_id > 0) {
			$u = get_userdata($user_id);
			if ($u) {
				$name = $u->display_name;
				$email = $u->user_email;
				$phone = (string) get_user_meta($user_id, 'billing_phone', true);
				$roles = implode(',', array_values((array) $u->roles));
			}
		}

		$t = dbr_table('carts');
		$now = current_time('mysql');
		$itemsJson = wp_json_encode($items, JSON_UNESCAPED_UNICODE);
		$total = (float) WC()->cart->get_cart_contents_total();

		$rowId = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t} WHERE cart_id = %s", $cart_id));
		if ($rowId) {
			// سبد تبدیل‌شده را به وضعیت open برنگردان
			$currentStatus = (string) $wpdb->get_var($wpdb->prepare("SELECT status FROM {$t} WHERE id = %d", (int) $rowId));
			$newStatus = ($currentStatus === 'converted') ? 'converted' : 'open';
			$wpdb->update(
				$t,
				array(
					'items'         => $itemsJson,
					'items_count'   => $count,
					'total'         => $total,
					'status'        => $newStatus,
					'last_activity' => $now,
					'name'          => $name,
					'phone'         => $phone,
					'email'         => $email,
					'roles'         => $roles,
				),
				array('id' => (int) $rowId),
				array('%s', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s'),
				array('%d')
			);
		} else {
			$wpdb->insert(
				$t,
				array(
					'cart_id'       => $cart_id,
					'user_id'       => $user_id > 0 ? (int) $user_id : null,
					'name'          => $name,
					'phone'         => $phone,
					'email'         => $email,
					'roles'         => $roles,
					'items'         => $itemsJson,
					'items_count'   => $count,
					'total'         => $total,
					'status'        => 'open',
					'last_activity' => $now,
					'created_at'    => $now,
				),
				array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%f', '%s', '%s', '%s')
			);
		}
	}

	/* ---------------- تبدیل سبد به سفارش ---------------- */

	/**
	 * در صفحه تشکر: یافتن سبد متناظر سفارش (کاربر/ایمیل/تلفن)،
	 * علامت‌گذاری converted و اعلام تبدیل به داریا + ثبت آمار دقیقه‌ها در لاگ
	 */
	public static function markCartConverted($order_id) {
		if (!class_exists('WooCommerce')) {
			return;
		}
		if (!Settings::instance()->get('sync_carts')) {
			return;
		}
		$order = function_exists('wc_get_order') ? wc_get_order((int) $order_id) : null;
		if (!$order instanceof WC_Order) {
			return;
		}

		global $wpdb;
		$t = dbr_table('carts');
		$row = null;

		$user_id = (int) $order->get_user_id();
		if ($user_id > 0) {
			$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t} WHERE cart_id = %s LIMIT 1", 'u_' . $user_id), ARRAY_A);
		}

		$email = (string) $order->get_billing_email();
		if (!$row && $email !== '') {
			$row = $wpdb->get_row(
				$wpdb->prepare("SELECT * FROM {$t} WHERE email = %s AND status <> 'converted' ORDER BY id DESC LIMIT 1", $email),
				ARRAY_A
			);
		}

		$phone = (string) $order->get_billing_phone();
		if (!$row && $phone !== '') {
			$row = $wpdb->get_row(
				$wpdb->prepare("SELECT * FROM {$t} WHERE phone = %s AND status <> 'converted' ORDER BY id DESC LIMIT 1", $phone),
				ARRAY_A
			);
		}

		if (!$row || (string) $row['status'] === 'converted') {
			return;
		}

		// آمار: فاصله آخرین فعالیت سبد تا ثبت سفارش (دقیقه)
		$minutes = null;
		$created = $order->get_date_created();
		if ($created && !empty($row['last_activity'])) {
			$tsOrder = strtotime($created->date('Y-m-d H:i:s'));
			$tsCart = strtotime((string) $row['last_activity']);
			if ($tsOrder && $tsCart) {
				$minutes = (int) round(max(0, ($tsOrder - $tsCart) / 60));
			}
		}

		$wpdb->update($t, array('status' => 'converted'), array('id' => (int) $row['id']), array('%s'), array('%d'));

		// اعلام تبدیل به داریا (فوری، در صورت شکست → صف)
		$payload = array(
			'cart_id'      => (string) $row['cart_id'],
			'wc_order_id'  => (int) $order_id,
			'converted_at' => gmdate('c'),
			'status'       => 'converted',
		);
		$response = ApiClient::instance()->request('v1/abandoned-carts/convert', $payload);
		$logStatus = 'ok';
		if (is_wp_error($response) || !ApiClient::ok($response)) {
			Queue::add('cart_convert', $payload);
			$logStatus = 'queued';
		}

		$message = sprintf(
			'سبد %s به سفارش #%d تبدیل شد%s',
			(string) $row['cart_id'],
			(int) $order_id,
			(null !== $minutes) ? ' — ' . $minutes . ' دقیقه پس از آخرین فعالیت' : ''
		);
		dbr_log('out', 'cart_convert', (string) $row['cart_id'], $logStatus, $message);
	}

	/* ---------------- پایش سبد رهاشده (cron) ---------------- */

	/** علامت‌گذاری سبد بیکار به‌عنوان abandoned و ارسال به داریا */
	public static function sweep() {
		if (!Settings::instance()->get('sync_carts')) {
			return array('skipped' => 'sync_carts_off', 'marked' => 0, 'pushed' => 0);
		}

		global $wpdb;
		$t = dbr_table('carts');
		$minutes = max(1, (int) Settings::instance()->get('cart_abandon_minutes', 20));
		$cutoff = gmdate('Y-m-d H:i:s', current_time('timestamp') - $minutes * 60);

		$rows = $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$t} WHERE status = 'open' AND last_activity IS NOT NULL AND last_activity < %s LIMIT 200", $cutoff),
			ARRAY_A
		);

		$pushed = 0;
		foreach ((array) $rows as $row) {
			$wpdb->update($t, array('status' => 'abandoned'), array('id' => (int) $row['id']), array('%s'), array('%d'));

			$payload = self::cartPayload(array_merge($row, array('status' => 'abandoned')));
			$response = ApiClient::instance()->request('v1/abandoned-carts', array('carts' => array($payload)));
			if (is_wp_error($response) || !ApiClient::ok($response)) {
				Queue::add('cart', $payload);
			} else {
				$pushed++;
			}
		}

		return array('marked' => count((array) $rows), 'pushed' => $pushed);
	}

	/* ---------------- کمکی ---------------- */

	/** ساخت payload سبد مطابق قرارداد API داریا از ردیف جدول */
	public static function cartPayload($row) {
		$items = json_decode((string) (isset($row['items']) ? $row['items'] : '[]'), true);
		if (!is_array($items)) {
			$items = array();
		}
		$userId = !empty($row['user_id']) ? (int) $row['user_id'] : 0;
		$roles = !empty($row['roles']) ? array_values(array_filter(explode(',', (string) $row['roles']))) : array();
		$lastActivity = (!empty($row['last_activity'])) ? (string) $row['last_activity'] : '';
		$status = (string) (isset($row['status']) ? $row['status'] : 'open');
		$currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '';

		return array(
			'cart_id'       => (string) (isset($row['cart_id']) ? $row['cart_id'] : ''),
			'customer_name' => (string) (isset($row['name']) ? $row['name'] : ''),
			'phone'         => (string) (isset($row['phone']) ? $row['phone'] : ''),
			'email'         => (string) (isset($row['email']) ? $row['email'] : ''),
			'wp_user_id'    => $userId > 0 ? $userId : null,
			'wp_user_login' => $userId > 0 ? (string) get_the_author_meta('user_login', $userId) : '',
			'wp_roles'      => $roles,
			'items'         => $items,
			'items_count'   => (int) (isset($row['items_count']) ? $row['items_count'] : 0),
			'total'         => (float) (isset($row['total']) ? $row['total'] : 0),
			'currency'      => $currency,
			'status'        => $status,
			'abandoned_at'  => ($status === 'abandoned') ? gmdate('c', current_time('timestamp')) : null,
			'last_activity' => $lastActivity !== '' ? gmdate('c', strtotime($lastActivity)) : null,
		);
	}

	/** شمارش سبد بر اساس وضعیت */
	public static function countsByStatus() {
		global $wpdb;
		$t = dbr_table('carts');
		$rows = $wpdb->get_results("SELECT status, COUNT(*) AS c FROM {$t} GROUP BY status", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL
		$out = array();
		foreach ((array) $rows as $r) {
			$out[(string) $r['status']] = (int) $r['c'];
		}
		return $out;
	}
}

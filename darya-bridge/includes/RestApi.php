<?php
/**
 * REST API افزونه (برای نرم‌افزار ویندوزی داریا)
 * Namespace: darya-bridge/v1
 * احراز: پارامتر یا هدر secret — مقایسه سخت‌گیرانه با hash_equals
 */

if (!defined('ABSPATH')) {
	exit;
}

class RestApi {

	public static function init() {
		add_action('rest_api_init', array(__CLASS__, 'register'));
	}

	public static function register() {
		register_rest_route('darya-bridge/v1', '/status', array(
			'methods'             => 'GET',
			'callback'            => array(__CLASS__, 'routeStatus'),
			'permission_callback' => '__return_true',
		));
		register_rest_route('darya-bridge/v1', '/run-sync', array(
			'methods'             => 'POST',
			'callback'            => array(__CLASS__, 'routeRunSync'),
			'permission_callback' => '__return_true',
		));
		register_rest_route('darya-bridge/v1', '/export/products', array(
			'methods'             => 'GET',
			'callback'            => array(__CLASS__, 'routeExportProducts'),
			'permission_callback' => '__return_true',
		));
		register_rest_route('darya-bridge/v1', '/export/orders', array(
			'methods'             => 'GET',
			'callback'            => array(__CLASS__, 'routeExportOrders'),
			'permission_callback' => '__return_true',
		));
		register_rest_route('darya-bridge/v1', '/export/carts', array(
			'methods'             => 'GET',
			'callback'            => array(__CLASS__, 'routeExportCarts'),
			'permission_callback' => '__return_true',
		));
		register_rest_route('darya-bridge/v1', '/export/roles', array(
			'methods'             => 'GET',
			'callback'            => array(__CLASS__, 'routeExportRoles'),
			'permission_callback' => '__return_true',
		));
	}

	/**
	 * بررسی secret — در صورت نامعتبر بودن پاسخ 403 با {ok:false}
	 *
	 * @param WP_REST_Request $request
	 * @return true|WP_REST_Response
	 */
	private static function guard($request) {
		$given = $request->get_param('secret');
		if (!is_string($given) || $given === '') {
			$header = $request->get_header('x-darya-secret');
			if (!is_string($header) || $header === '') {
				$header = $request->get_header('secret');
			}
			$given = is_string($header) ? $header : '';
		}
		$expected = (string) Settings::instance()->get('secret');
		if ($expected !== '' && $given !== '' && hash_equals($expected, $given)) {
			return true;
		}
		return new WP_REST_Response(array(
			'ok'    => false,
			'error' => array(
				'code'    => 'forbidden',
				'message' => 'کلید امنیتی نامعتبر است',
			),
		), 403);
	}

	/* ---------------- /status ---------------- */

	public static function routeStatus($request) {
		$guard = self::guard($request);
		if (true !== $guard) {
			return $guard;
		}
		return array(
			'ok'              => true,
			'version'         => DBR_VERSION,
			'queue'           => Queue::counts(),
			'carts'           => Carts::countsByStatus(),
			'last_log'        => dbr_log_last(),
			'settings_masked' => Settings::instance()->masked(),
			'woocommerce'     => class_exists('WooCommerce'),
			'site'            => home_url(''),
			'time'            => gmdate('c'),
		);
	}

	/* ---------------- /run-sync ---------------- */

	public static function routeRunSync($request) {
		$guard = self::guard($request);
		if (true !== $guard) {
			return $guard;
		}
		$result = array(
			'queue'  => Queue::flush(50),
			'roles'  => RolesSync::syncAllUsers(),
			'carts'  => Carts::sweep(),
		);
		return array(
			'ok'     => true,
			'result' => $result,
			'time'   => gmdate('c'),
		);
	}

	/* ---------------- /export/products ---------------- */

	public static function routeExportProducts($request) {
		$guard = self::guard($request);
		if (true !== $guard) {
			return $guard;
		}
		if (!class_exists('WooCommerce')) {
			return array('ok' => false, 'error' => array('code' => 'no_woocommerce', 'message' => 'ووکامرس فعال نیست'));
		}

		$page = max(1, absint($request->get_param('page') ? $request->get_param('page') : 1));
		$perPage = min(200, max(1, absint($request->get_param('per_page') ? $request->get_param('per_page') : 50)));

		$result = wc_get_products(array(
			'status'   => array('publish', 'pending', 'draft', 'private', 'future'),
			'limit'    => $perPage,
			'page'     => $page,
			'orderby'  => 'ID',
			'order'    => 'ASC',
			'paginate' => true,
		));

		$objects = array();
		$total = 0;
		$totalPages = 1;

		if (is_array($result)) {
			$objects = isset($result['objects']) ? $result['objects'] : $result;
			$total = isset($result['total']) ? (int) $result['total'] : count($objects);
			$totalPages = isset($result['max_num_pages']) ? max(1, (int) $result['max_num_pages']) : 1;
		} elseif (is_object($result)) {
			$objects = isset($result->objects) ? $result->objects : array();
			$total = isset($result->total) ? (int) $result->total : count($objects);
			$totalPages = isset($result->max_num_pages) ? max(1, (int) $result->max_num_pages) : 1;
		}

		$items = array();
		foreach ((array) $objects as $product) {
			$payload = Sync::productPayload($product);
			if ($payload) {
				$items[] = $payload;
			}
		}

		return array(
			'ok'          => true,
			'items'       => $items,
			'page'        => $page,
			'total_pages' => $totalPages,
			'total'       => $total,
		);
	}

	/* ---------------- /export/orders ---------------- */

	public static function routeExportOrders($request) {
		$guard = self::guard($request);
		if (true !== $guard) {
			return $guard;
		}
		if (!class_exists('WooCommerce')) {
			return array('ok' => false, 'error' => array('code' => 'no_woocommerce', 'message' => 'ووکامرس فعال نیست'));
		}

		$page = max(1, absint($request->get_param('page') ? $request->get_param('page') : 1));
		$perPage = min(100, max(1, absint($request->get_param('per_page') ? $request->get_param('per_page') : 20)));

		$args = array(
			'limit'    => $perPage,
			'page'     => $page,
			'type'     => 'shop_order',
			'status'   => array_keys(wc_get_order_statuses()),
			'orderby'  => 'date',
			'order'    => 'ASC',
			'paginate' => true,
		);

		$since = trim((string) $request->get_param('since'));
		if ($since !== '' && strtotime($since)) {
			$args['date_modified'] = '>' . $since;
		}

		$result = wc_get_orders($args);

		$orders = array();
		$total = 0;
		$totalPages = 1;

		if (is_array($result)) {
			$orders = isset($result['orders']) ? $result['orders'] : $result;
			$total = isset($result['total']) ? (int) $result['total'] : count($orders);
			$totalPages = isset($result['max_num_pages']) ? max(1, (int) $result['max_num_pages']) : 1;
		} elseif (is_object($result)) {
			$orders = isset($result->orders) ? $result->orders : array();
			$total = isset($result->total) ? (int) $result->total : count($orders);
			$totalPages = isset($result->max_num_pages) ? max(1, (int) $result->max_num_pages) : 1;
		}

		$items = array();
		foreach ((array) $orders as $order) {
			$payload = Sync::orderPayload($order);
			if ($payload) {
				$items[] = $payload;
			}
		}

		return array(
			'ok'          => true,
			'items'       => $items,
			'page'        => $page,
			'total_pages' => $totalPages,
			'total'       => $total,
		);
	}

	/* ---------------- /export/carts ---------------- */

	public static function routeExportCarts($request) {
		$guard = self::guard($request);
		if (true !== $guard) {
			return $guard;
		}

		global $wpdb;
		$t = dbr_table('carts');

		$since = trim((string) $request->get_param('since'));
		$where = "WHERE status IN ('open','abandoned')";
		$params = array();

		if ($since !== '' && strtotime($since)) {
			// تبدیل ISO8601 به قامت ذخیره‌شده در جدول (زمان محلی سایت)
			$sinceSql = gmdate('Y-m-d H:i:s', strtotime($since) + (int) (get_option('gmt_offset') * HOUR_IN_SECONDS));
			$where .= ' AND last_activity IS NOT NULL AND last_activity > %s';
			$params[] = $sinceSql;
		}

		$sql = "SELECT * FROM {$t} {$where} ORDER BY last_activity DESC LIMIT 500";
		if ($params) {
			$sql = $wpdb->prepare($sql, $params); // phpcs:ignore WordPress.DB.PreparedSQL
		}
		$rows = $wpdb->get_results($sql, ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL

		$items = array();
		foreach ((array) $rows as $row) {
			$items[] = Carts::cartPayload($row);
		}

		return array(
			'ok'    => true,
			'items' => $items,
			'count' => count($items),
		);
	}

	/* ---------------- /export/roles ---------------- */

	public static function routeExportRoles($request) {
		$guard = self::guard($request);
		if (true !== $guard) {
			return $guard;
		}

		$page = max(1, absint($request->get_param('page') ? $request->get_param('page') : 1));
		$perPage = min(200, max(1, absint($request->get_param('per_page') ? $request->get_param('per_page') : 100)));

		$query = new WP_User_Query(array(
			'number'      => $perPage,
			'paged'       => $page,
			'orderby'     => 'ID',
			'order'       => 'ASC',
			'count_total' => true,
		));

		$total = (int) $query->get_total();
		$items = array();
		foreach ((array) $query->get_results() as $user) {
			$payload = RolesSync::userPayload($user->ID);
			if ($payload) {
				$items[] = $payload;
			}
		}

		return array(
			'ok'          => true,
			'items'       => $items,
			'page'        => $page,
			'total_pages' => max(1, (int) ceil($total / $perPage)),
			'total'       => $total,
		);
	}
}

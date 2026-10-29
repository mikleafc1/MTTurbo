<?php
/**
 * صف رویدادها — در صورت شکست ارسال فوری، رویداد اینجا نگه داشته می‌شود
 * و توسط cron (هر ۵ دقیقه) یا اجرای دستی به داریا ارسال می‌گردد.
 */

if (!defined('ABSPATH')) {
	exit;
}

class Queue {

	/** افزودن رویداد به صف */
	public static function add($eventType, array $payload) {
		global $wpdb;
		$wpdb->insert(
			dbr_table('queue'),
			array(
				'event_type' => substr((string) $eventType, 0, 40),
				'payload'    => wp_json_encode($payload, JSON_UNESCAPED_UNICODE),
				'attempts'   => 0,
				'created_at' => current_time('mysql'),
			),
			array('%s', '%s', '%d', '%s')
		);
		return (int) $wpdb->insert_id;
	}

	/**
	 * ارسال رویدادهای صف به داریا (حداکثر ۵۰ رویداد در هر اجرا)
	 *
	 * @param int $limit
	 * @return array ['sent'=>int,'failed'=>int]
	 */
	public static function flush($limit = 50) {
		global $wpdb;
		$out = array('sent' => 0, 'failed' => 0);

		if (!Settings::instance()->configured()) {
			$out['skipped'] = 'unconfigured';
			return $out;
		}

		$t = dbr_table('queue');
		$rows = $wpdb->get_results(
			$wpdb->prepare("SELECT * FROM {$t} WHERE attempts < %d ORDER BY id ASC LIMIT %d", DBR_QUEUE_MAX_ATTEMPTS, max(1, (int) $limit)),
			ARRAY_A
		);

		foreach ((array) $rows as $row) {
			$payload = json_decode((string) $row['payload'], true);
			if (!is_array($payload)) {
				$payload = array();
			}

			$response = self::send($row['event_type'], $payload);

			if (is_wp_error($response) || !ApiClient::ok($response)) {
				$msg = is_wp_error($response)
					? $response->get_error_message()
					: (isset($response['error']['message']) && is_string($response['error']['message'])
						? $response['error']['message']
						: 'خطای ناشناخته در ارسال به داریا');
				// ثبت خطا و افزایش شمارنده تلاش‌ها
				$wpdb->query(
					$wpdb->prepare(
						"UPDATE {$t} SET attempts = attempts + 1, last_error = %s WHERE id = %d",
						mb_substr($msg, 0, 500),
						(int) $row['id']
					)
				);
				$out['failed']++;
			} else {
				// موفق — حذف از صف
				$wpdb->delete($t, array('id' => (int) $row['id']), array('%d'));
				$out['sent']++;
			}
		}

		return $out;
	}

	/** ارسال یک رویداد به اندپوینت متناظر در داریا */
	private static function send($eventType, array $payload) {
		$client = ApiClient::instance();
		switch ((string) $eventType) {
			case 'order':
				return $client->request('v1/orders', array('order' => $payload));
			case 'product':
				return $client->request('v1/products/upsert', array('products' => array($payload)));
			case 'customer':
				return $client->request('v1/customers/upsert', array('customers' => array($payload)));
			case 'wp_user':
				return $client->request('v1/users/sync', array('users' => array($payload)));
			case 'cart':
				return $client->request('v1/abandoned-carts', array('carts' => array($payload)));
			case 'cart_convert':
				return $client->request('v1/abandoned-carts/convert', $payload);
			default:
				return new WP_Error('dbr_unknown_event', 'رویداد ناشناخته: ' . (string) $eventType);
		}
	}

	/** آمار صف */
	public static function counts() {
		global $wpdb;
		$t = dbr_table('queue');

		$total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t}"); // phpcs:ignore WordPress.DB.PreparedSQL
		$failed = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE attempts > 0"); // phpcs:ignore WordPress.DB.PreparedSQL

		$byType = array();
		$rows = $wpdb->get_results("SELECT event_type, COUNT(*) AS c FROM {$t} GROUP BY event_type", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL
		foreach ((array) $rows as $r) {
			$byType[(string) $r['event_type']] = (int) $r['c'];
		}

		return array(
			'total'    => $total,
			'failed'   => $failed,
			'by_type'  => $byType,
		);
	}

	/** خالی کردن کامل صف */
	public static function clear() {
		global $wpdb;
		$t = dbr_table('queue');
		$wpdb->query("TRUNCATE TABLE {$t}"); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
		return true;
	}
}

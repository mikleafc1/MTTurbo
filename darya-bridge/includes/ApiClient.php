<?php
/**
 * کلاینت HTTP برای REST API نرم‌افزار داریا
 * آدرس پایه: {DARYA_URL}/api/index.php?path=v1/<endpoint>
 * احراز: هدر X-Api-Key
 */

if (!defined('ABSPATH')) {
	exit;
}

class ApiClient {

	/** @var ApiClient|null */
	private static $instance = null;

	public static function instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * ارسال درخواست به داریا
	 *
	 * @param string $path   مثل 'v1/orders'
	 * @param array  $body   بدنه (به JSON تبدیل می‌شود)
	 * @param string $method POST|GET
	 * @return array|WP_Error آرایه پاسخ دیکودشده یا WP_Error
	 */
	public function request($path, $body = array(), $method = 'POST') {
		$settings = Settings::instance();
		$base = untrailingslashit((string) $settings->get('darya_url'));
		$key = (string) $settings->get('api_key');

		if ($base === '') {
			$err = new WP_Error('dbr_no_url', 'آدرس داریا در تنظیمات افزونه وارد نشده است');
			dbr_log('out', 'api', $path, 'error', $err->get_error_message());
			return $err;
		}
		if ($key === '') {
			$err = new WP_Error('dbr_no_key', 'کلید API داریا در تنظیمات افزونه وارد نشده است');
			dbr_log('out', 'api', $path, 'error', $err->get_error_message());
			return $err;
		}

		$url = $base . '/api/index.php?path=' . rawurlencode((string) $path);

		$args = array(
			'timeout'   => 20,
			'sslverify' => true,
			'headers'   => array(
				'X-Api-Key'   => $key,
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
			),
		);

		if (strtoupper((string) $method) === 'GET') {
			$response = wp_remote_get($url, $args);
		} else {
			$args['body'] = wp_json_encode($body, JSON_UNESCAPED_UNICODE);
			$response = wp_remote_post($url, $args);
		}

		if (is_wp_error($response)) {
			dbr_log('out', 'api', $path, 'error', $response->get_error_message());
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$raw = (string) wp_remote_retrieve_body($response);
		$data = json_decode($raw, true);

		if (!is_array($data)) {
			$msg = 'پاسخ نامعتبر از داریا (کد HTTP ' . $code . '): ' . mb_substr($raw, 0, 200);
			dbr_log('out', 'api', $path, 'error', $msg);
			return new WP_Error('dbr_bad_json', 'پاسخ نامعتبر از داریا (کد HTTP ' . $code . ')', array('status' => $code));
		}

		if (!empty($data['ok'])) {
			dbr_log('out', 'api', $path, 'ok', $path . ' — پاسخ موفق (کد ' . $code . ')');
		} else {
			$msg = 'خطای داریا (کد ' . $code . ')';
			if (isset($data['error']['message']) && is_string($data['error']['message'])) {
				$msg .= ': ' . $data['error']['message'];
			} elseif (isset($data['error'])) {
				$msg .= ': ' . wp_json_encode($data['error'], JSON_UNESCAPED_UNICODE);
			}
			dbr_log('out', 'api', $path, 'error', $msg);
		}

		return $data;
	}

	/** آیا پاسخ موفق بوده؟ ({"ok": true, ...}) */
	public static function ok($response) {
		return is_array($response) && !empty($response['ok']);
	}
}

<?php
/**
 * تنظیمات پل داریا (Singleton)
 */

if (!defined('ABSPATH')) {
	exit;
}

class Settings {

	/** @var Settings|null */
	private static $instance = null;

	/** @var array|null کش تنظیمات */
	private $options = null;

	private function __construct() {
	}

	private function __clone() {
	}

	public function __wakeup() {
		throw new Exception('Cannot unserialize singleton');
	}

	public static function instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** مقادیر پیش‌فرض */
	public static function defaults() {
		return array(
			'darya_url'            => '',
			'api_key'              => '',
			'secret'               => '',
			'sync_products'        => 1,
			'sync_orders'          => 1,
			'sync_customers'       => 1,
			'sync_roles'           => 1, // همگام‌سازی نقش‌های کاربران (مهم)
			'sync_carts'           => 1,
			'cart_abandon_minutes' => 20,
			'wp_cron_interval'     => 300,
		);
	}

	/** همه تنظیمات (با ادغام پیش‌فرض‌ها) */
	public function all() {
		if (null === $this->options) {
			$saved = get_option('darya_bridge_settings', array());
			if (!is_array($saved)) {
				$saved = array();
			}
			$this->options = array_merge(self::defaults(), $saved);
		}
		return $this->options;
	}

	/** دسترسی به یک کلید */
	public function get($key, $fallback = null) {
		$all = $this->all();
		return array_key_exists($key, $all) ? $all[$key] : $fallback;
	}

	/** آیا آدرس و کلید API داریا تنظیم شده؟ */
	public function configured() {
		return (string) $this->get('darya_url') !== '' && (string) $this->get('api_key') !== '';
	}

	/** تولید کلید امنیتی جدید (۳۲ کاراکتر hex) */
	public function regenerateSecret() {
		$secret = bin2hex(random_bytes(16));
		$all = $this->all();
		$all['secret'] = $secret;
		update_option('darya_bridge_settings', $all, false);
		$this->options = $all;
		return $secret;
	}

	/** نسخه ماسک‌شده برای نمایش در REST /status */
	public function masked() {
		$all = $this->all();

		$key = (string) $all['api_key'];
		$maskKey = $key === '' ? '' : (strlen($key) > 10 ? substr($key, 0, 6) . '…' . substr($key, -4) : '***');

		$sec = (string) $all['secret'];
		$maskSec = $sec === '' ? '' : (strlen($sec) >= 12 ? substr($sec, 0, 4) . '…' . substr($sec, -4) : '***');

		return array(
			'darya_url'            => (string) $all['darya_url'],
			'api_key'              => $maskKey,
			'secret'               => $maskSec,
			'sync_products'        => (int) $all['sync_products'],
			'sync_orders'          => (int) $all['sync_orders'],
			'sync_customers'       => (int) $all['sync_customers'],
			'sync_roles'           => (int) $all['sync_roles'],
			'sync_carts'           => (int) $all['sync_carts'],
			'cart_abandon_minutes' => (int) $all['cart_abandon_minutes'],
			'wp_cron_interval'     => (int) $all['wp_cron_interval'],
		);
	}

	/**
	 * پاک‌سازی ورودی فرم تنظیمات (sanitize callback)
	 *
	 * @param mixed $input ورودی خام فرم
	 * @return array
	 */
	public static function sanitize($input) {
		$old = get_option('darya_bridge_settings', array());
		if (!is_array($old)) {
			$old = array();
		}
		$input = is_array($input) ? $input : array();
		$out = self::defaults();

		$out['darya_url'] = isset($input['darya_url'])
			? esc_url_raw(trim((string) $input['darya_url']))
			: (isset($old['darya_url']) ? (string) $old['darya_url'] : '');

		// کلید API عیناً ذخیره می‌شود (برای احراز هویت داریا باید دست‌نخورده بماند)
		$out['api_key'] = isset($input['api_key'])
			? trim((string) $input['api_key'])
			: (isset($old['api_key']) ? (string) $old['api_key'] : '');

		// کلید امنیتی: اگر خالی ارسال شد قبلی حفظ شود
		$secret = isset($input['secret']) ? trim((string) $input['secret']) : '';
		if ($secret === '' && isset($old['secret'])) {
			$secret = (string) $old['secret'];
		}
		$out['secret'] = preg_replace('/[^a-f0-9]/i', '', $secret);

		foreach (array('sync_products', 'sync_orders', 'sync_customers', 'sync_roles', 'sync_carts') as $flag) {
			$out[$flag] = empty($input[$flag]) ? 0 : 1;
		}

		$out['cart_abandon_minutes'] = max(1, absint(isset($input['cart_abandon_minutes']) ? $input['cart_abandon_minutes'] : 20));
		$out['wp_cron_interval'] = max(60, absint(isset($input['wp_cron_interval']) ? $input['wp_cron_interval'] : 300));

		return $out;
	}
}

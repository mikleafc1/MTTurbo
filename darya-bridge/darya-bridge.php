<?php
/**
 * Plugin Name: Darya Bridge
 * Description: همگام‌سازی ووکامرس با نرم‌افزار حسابداری داریا (محصولات، سفارش‌ها، مشتریان، نقش‌های کاربران و سبد رهاشده)
 * Version: 1.0.0
 * Author: Darya Bridge
 * Text Domain: darya-bridge
 * Requires at least: 6.0
 * Requires PHP: 7.4
 *
 * پل داریا — اتصال فروشگاه ووکامرس به حسابداری داریا
 */

if (!defined('ABSPATH')) {
	exit;
}

define('DBR_VERSION', '1.0.0');
define('DBR_FILE', __FILE__);
define('DBR_DIR', plugin_dir_path(__FILE__));
// حداکثر تلاش برای هر رویداد صف (مطابق رفتار داریا)
define('DBR_QUEUE_MAX_ATTEMPTS', 5);

require_once DBR_DIR . 'includes/Settings.php';
require_once DBR_DIR . 'includes/ApiClient.php';
require_once DBR_DIR . 'includes/Queue.php';
require_once DBR_DIR . 'includes/Sync.php';
require_once DBR_DIR . 'includes/RolesSync.php';
require_once DBR_DIR . 'includes/Carts.php';
require_once DBR_DIR . 'includes/RestApi.php';
require_once DBR_DIR . 'includes/AdminPage.php';

/* ---------------------------------------------------------------------------
 * توابع کمکی جدول‌ها و لاگ
 * ------------------------------------------------------------------------- */

/** نام کامل جدول: queue | carts | log */
function dbr_table($name) {
	global $wpdb;
	return $wpdb->prefix . 'darya_' . (string) $name;
}

/** ثبت لاگ در جدول darya_log */
function dbr_log($direction, $entity, $ref, $status, $message = '') {
	global $wpdb;
	$wpdb->insert(
		dbr_table('log'),
		array(
			'direction'  => substr((string) $direction, 0, 10),
			'entity'     => substr((string) $entity, 0, 40),
			'ref'        => substr((string) $ref, 0, 80),
			'status'     => substr((string) $status, 0, 20),
			'message'    => (string) $message,
			'created_at' => current_time('mysql'),
		),
		array('%s', '%s', '%s', '%s', '%s', '%s')
	);
}

/** آخرین ردیف‌های لاگ */
function dbr_log_recent($limit = 20) {
	global $wpdb;
	$t = dbr_table('log');
	$limit = max(1, (int) $limit);
	$rows = $wpdb->get_results("SELECT * FROM {$t} ORDER BY id DESC LIMIT {$limit}", ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL
	return is_array($rows) ? $rows : array();
}

/** آخرین لاگ ثبت‌شده */
function dbr_log_last() {
	$rows = dbr_log_recent(1);
	return !empty($rows) ? $rows[0] : null;
}

/* ---------------------------------------------------------------------------
 * ساخت جدول‌ها (dbDelta)
 * ------------------------------------------------------------------------- */

function dbr_install_tables() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset = $wpdb->get_charset_collate();
	$queue = dbr_table('queue');
	$carts = dbr_table('carts');
	$log   = dbr_table('log');

	$sql = array();
	$sql[] = "CREATE TABLE {$queue} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		event_type VARCHAR(40) NOT NULL DEFAULT '',
		payload LONGTEXT NULL,
		attempts TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
		last_error TEXT NULL,
		created_at DATETIME NULL,
		PRIMARY KEY  (id),
		KEY created_at (created_at),
		KEY event_type (event_type)
	) {$charset};";

	$sql[] = "CREATE TABLE {$carts} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		cart_id VARCHAR(80) NOT NULL DEFAULT '',
		user_id BIGINT(20) UNSIGNED NULL,
		name VARCHAR(150) NULL,
		phone VARCHAR(30) NULL,
		email VARCHAR(120) NULL,
		roles VARCHAR(190) NULL,
		items LONGTEXT NULL,
		items_count INT(11) NOT NULL DEFAULT 0,
		total DECIMAL(18,2) NOT NULL DEFAULT 0,
		status VARCHAR(20) NOT NULL DEFAULT 'open',
		last_activity DATETIME NULL,
		created_at DATETIME NULL,
		PRIMARY KEY  (id),
		UNIQUE KEY cart_id (cart_id),
		KEY status (status),
		KEY last_activity (last_activity)
	) {$charset};";

	$sql[] = "CREATE TABLE {$log} (
		id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
		direction VARCHAR(10) NOT NULL DEFAULT '',
		entity VARCHAR(40) NOT NULL DEFAULT '',
		ref VARCHAR(80) NOT NULL DEFAULT '',
		status VARCHAR(20) NOT NULL DEFAULT '',
		message TEXT NULL,
		created_at DATETIME NULL,
		PRIMARY KEY  (id),
		KEY created_at (created_at),
		KEY entity (entity)
	) {$charset};";

	foreach ($sql as $query) {
		dbDelta($query);
	}
}

/* ---------------------------------------------------------------------------
 * فعال‌سازی / غیرفعال‌سازی
 * ------------------------------------------------------------------------- */

function dbr_activate() {
	dbr_install_tables();
	update_option('darya_bridge_db_version', DBR_VERSION, false);

	// تولید کلید امنیتی ۳۲ کاراکتری hex در صورت خالی بودن
	$settings = Settings::instance();
	if ((string) $settings->get('secret') === '') {
		$settings->regenerateSecret();
	}

	dbr_schedule_crons();
}

function dbr_deactivate() {
	wp_clear_scheduled_hook('darya_bridge_flush_queue');
	wp_clear_scheduled_hook('darya_bridge_carts_sweep');
	wp_clear_scheduled_hook('darya_bridge_roles_full');
}

register_activation_hook(__FILE__, 'dbr_activate');
register_deactivation_hook(__FILE__, 'dbr_deactivate');

/** ارتقای ساختار در صورت تغییر نسخه */
function dbr_maybe_upgrade() {
	if (get_option('darya_bridge_db_version') !== DBR_VERSION) {
		dbr_install_tables();
		update_option('darya_bridge_db_version', DBR_VERSION, false);
		$settings = Settings::instance();
		if ((string) $settings->get('secret') === '') {
			$settings->regenerateSecret();
		}
	}
}
add_action('admin_init', 'dbr_maybe_upgrade');

/* ---------------------------------------------------------------------------
 * زمان‌بندی WP-Cron
 * ------------------------------------------------------------------------- */

function dbr_cron_schedules($schedules) {
	$interval = max(60, (int) Settings::instance()->get('wp_cron_interval', 300));
	$schedules['darya_5min']   = array('interval' => $interval, 'display' => 'هر ۵ دقیقه (پل داریا)');
	$schedules['darya_15min']  = array('interval' => 900,    'display' => 'هر ۱۵ دقیقه (پل داریا)');
	$schedules['darya_hourly'] = array('interval' => 3600,   'display' => 'ساعتی (پل داریا)');
	return $schedules;
}
add_filter('cron_schedules', 'dbr_cron_schedules');

function dbr_schedule_crons() {
	if (!wp_next_scheduled('darya_bridge_flush_queue')) {
		wp_schedule_event(time() + 120, 'darya_5min', 'darya_bridge_flush_queue');
	}
	if (!wp_next_scheduled('darya_bridge_carts_sweep')) {
		wp_schedule_event(time() + 180, 'darya_15min', 'darya_bridge_carts_sweep');
	}
	if (!wp_next_scheduled('darya_bridge_roles_full')) {
		wp_schedule_event(time() + 300, 'darya_hourly', 'darya_bridge_roles_full');
	}
}
add_action('init', 'dbr_schedule_crons');

add_action('darya_bridge_flush_queue', array('Queue', 'flush'));
add_action('darya_bridge_carts_sweep', array('Carts', 'sweep'));
add_action('darya_bridge_roles_full', array('RolesSync', 'cronSyncAllUsers'));

/* ---------------------------------------------------------------------------
 * اعلان نبود ووکامرس
 * ------------------------------------------------------------------------- */

function dbr_woo_missing_notice() {
	echo '<div class="notice notice-error"><p><strong>پل داریا:</strong> افزونه ووکامرس فعال نیست. برای همگام‌سازی محصولات، سفارش‌ها و سبد رهاشده ابتدا ووکامرس را نصب و فعال کنید. (تنظیمات و REST API افزونه همچنان در دسترس است.)</p></div>';
}

/* ---------------------------------------------------------------------------
 * راه‌اندازی
 * ------------------------------------------------------------------------- */

function dbr_boot() {
	Settings::instance();
	AdminPage::init();
	RestApi::init();
	RolesSync::init(); // نقش‌های کاربران وابسته به ووکامرس نیست

	if (class_exists('WooCommerce')) {
		Sync::init();
		Carts::init();
	} else {
		add_action('admin_notices', 'dbr_woo_missing_notice');
	}
}
add_action('plugins_loaded', 'dbr_boot', 20);

// اعلام سازگاری با HPOS (جدول سفارش‌های سفارشی ووکامرس)
add_action('before_woocommerce_init', function () {
	if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});

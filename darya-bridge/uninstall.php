<?php
/**
 * حذف کامل افزونه: پاک‌سازی جدول‌ها، گزینه‌ها و زمان‌بندی‌ها
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
	exit;
}

global $wpdb;

// حذف جدول‌های اختصاصی
foreach (array('darya_queue', 'darya_carts', 'darya_log') as $table_name) {
	$table = $wpdb->prefix . $table_name;
	$wpdb->query("DROP TABLE IF EXISTS `{$table}`"); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery
}

// حذف گزینه‌ها
delete_option('darya_bridge_settings');
delete_option('darya_bridge_db_version');

// حذف رویدادهای زمان‌بندی‌شده
wp_clear_scheduled_hook('darya_bridge_flush_queue');
wp_clear_scheduled_hook('darya_bridge_carts_sweep');
wp_clear_scheduled_hook('darya_bridge_roles_full');

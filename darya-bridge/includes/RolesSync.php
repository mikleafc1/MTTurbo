<?php
/**
 * همگام‌سازی نقش‌های کاربران وردپرس (ویژگی کلیدی)
 * با هر تغییر نقش، اطلاعات کاربر فوراً به داریا (v1/users/sync) ارسال می‌شود.
 * داریا بر اساس role-map نقش وردپرس را به نقش داریا نگاشت و کاربر/مشتری متناظر را می‌سازد.
 */

if (!defined('ABSPATH')) {
	exit;
}

class RolesSync {

	public static function init() {
		add_action('user_register', array(__CLASS__, 'pushUserRoles'), 20, 1);
		add_action('profile_update', array(__CLASS__, 'pushUserRoles'), 20, 1);
		add_action('add_user_role', array(__CLASS__, 'pushUserRoles'), 20, 1);
		add_action('remove_user_role', array(__CLASS__, 'pushUserRoles'), 20, 1);
		add_action('set_user_role', array(__CLASS__, 'pushUserRoles'), 20, 1);
	}

	/** payload کاربر مطابق قرارداد API داریا */
	public static function userPayload($user_id) {
		$u = get_userdata((int) $user_id);
		if (!$u) {
			return null;
		}
		return array(
			'wp_user_id'   => (int) $u->ID,
			'username'     => $u->user_login,
			'email'        => $u->user_email,
			'display_name' => $u->display_name,
			'roles'        => array_values((array) $u->roles),
			'phone'        => (string) get_user_meta($u->ID, 'billing_phone', true),
		);
	}

	/**
	 * ارسال فوری نقش‌های یک کاربر؛ در صورت شکست → صف (event: wp_user)
	 *
	 * @param int   $user_id
	 * @param mixed $unused پارامترهای اضافی هوک‌ها (role و ...)
	 */
	public static function pushUserRoles($user_id, $unused = null) {
		if (!Settings::instance()->get('sync_roles')) {
			return;
		}
		$payload = self::userPayload((int) $user_id);
		if (!$payload) {
			return;
		}
		$response = ApiClient::instance()->request('v1/users/sync', array('users' => array($payload)));
		if (is_wp_error($response) || !ApiClient::ok($response)) {
			Queue::add('wp_user', $payload);
		}
	}

	/** اجرای ساعتی cron — همگام‌سازی کامل نقش‌های همه کاربران */
	public static function cronSyncAllUsers() {
		if (!Settings::instance()->get('sync_roles')) {
			return;
		}
		self::syncAllUsers();
	}

	/**
	 * همگام‌سازی کامل همه کاربران (دسته‌های ۱۰۰تایی، حداکثر ۵۰۰ کاربر در هر اجرا)
	 * استفاده: cron ساعتی، REST /run-sync و دکمه صفحه مدیریت
	 *
	 * @param int $limit سقف کاربران در هر اجرا
	 * @return array
	 */
	public static function syncAllUsers($limit = 500) {
		if (!Settings::instance()->get('sync_roles')) {
			return array('skipped' => 'sync_roles_off', 'sent' => 0, 'failed' => 0, 'users' => 0);
		}
		if (!Settings::instance()->configured()) {
			return array('skipped' => 'unconfigured', 'sent' => 0, 'failed' => 0, 'users' => 0);
		}

		$limit = max(1, (int) $limit);
		$sent = 0;
		$failed = 0;
		$users = 0;

		for ($offset = 0; $offset < $limit; $offset += 100) {
			$batch = get_users(array(
				'number'  => 100,
				'offset'  => $offset,
				'orderby' => 'ID',
				'order'   => 'ASC',
			));
			if (empty($batch)) {
				break;
			}

			$payloads = array();
			foreach ($batch as $u) {
				$p = self::userPayload($u->ID);
				if ($p) {
					$payloads[] = $p;
				}
			}
			$users += count($payloads);

			$response = ApiClient::instance()->request('v1/users/sync', array('users' => $payloads));
			if (is_wp_error($response) || !ApiClient::ok($response)) {
				$failed += count($payloads);
				// در صف قرار بگیرد تا در اجرای بعدی cron ارسال شود
				foreach ($payloads as $p) {
					Queue::add('wp_user', $p);
				}
			} else {
				$sent += count($payloads);
			}

			if (count($batch) < 100) {
				break;
			}
		}

		return array('sent' => $sent, 'failed' => $failed, 'users' => $users);
	}
}

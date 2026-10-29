<?php
/**
 * صفحه مدیریت پل داریا (زیرمنوی ووکامرس) — تمام برچسب‌ها فارسی
 */

if (!defined('ABSPATH')) {
        exit;
}

class AdminPage {

        const PAGE_SLUG = 'darya-bridge';

        public static function init() {
                add_action('admin_menu', array(__CLASS__, 'menu'));
                add_action('admin_init', array(__CLASS__, 'adminInit'));
                add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));

                add_action('admin_post_dbr_regenerate_secret', array(__CLASS__, 'handleRegenerateSecret'));
                add_action('admin_post_dbr_flush_queue', array(__CLASS__, 'handleFlushQueue'));
                add_action('admin_post_dbr_clear_queue', array(__CLASS__, 'handleClearQueue'));
                add_action('admin_post_dbr_save_role_map', array(__CLASS__, 'handleSaveRoleMap'));
                add_action('wp_ajax_dbr_test_connection', array(__CLASS__, 'ajaxTestConnection'));
        }

        /* ---------------- منو و تنظیمات ---------------- */

        public static function menu() {
                if (class_exists('WooCommerce')) {
                        add_submenu_page(
                                'woocommerce',
                                'پل داریا',
                                'پل داریا',
                                'manage_woocommerce',
                                self::PAGE_SLUG,
                                array(__CLASS__, 'render')
                        );
                } else {
                        // در نبود ووکامرس، منوی مستقل نمایش داده شود
                        add_menu_page(
                                'پل داریا',
                                'پل داریا',
                                'manage_woocommerce',
                                self::PAGE_SLUG,
                                array(__CLASS__, 'render'),
                                'dashicons-randomize',
                                58
                        );
                }
        }

        public static function adminInit() {
                register_setting('darya_bridge', 'darya_bridge_settings', array(
                        'type'              => 'array',
                        'sanitize_callback' => array('Settings', 'sanitize'),
                        'show_in_rest'      => false,
                ));
        }

        public static function assets($hook) {
                if (strpos((string) $hook, self::PAGE_SLUG) === false) {
                        return;
                }
                wp_enqueue_style('dbr-admin', plugins_url('assets/admin.css', DBR_FILE), array(), DBR_VERSION);
                wp_register_script('dbr-admin', '', array(), DBR_VERSION, true);
                wp_enqueue_script('dbr-admin');
                wp_add_inline_script('dbr-admin', self::inlineJs());
        }

        /* ---------------- نقشه نقش‌ها ---------------- */

        /** دریافت نقشه نقش‌های فعلی از داریا (v1/role-map) */
        private static function roleMap() {
                static $map = null;
                if (null !== $map) {
                        return $map;
                }
                $map = array();
                if (Settings::instance()->configured()) {
                        $response = ApiClient::instance()->request('v1/role-map', array(), 'GET');
                        if (ApiClient::ok($response) && isset($response['data']['map']) && is_array($response['data']['map'])) {
                                $map = $response['data']['map'];
                        }
                }
                return $map;
        }

        /* ---------------- رندر صفحه ---------------- */

        public static function render() {
                if (!current_user_can('manage_woocommerce')) {
                        return;
                }

                $settings = Settings::instance();
                $opts = $settings->all();
                $map = self::roleMap();

                // مقادیر یکتای نقش‌های داریا برای پیشنهاد (datalist)
                $daryaRoleValues = array();
                foreach ((array) $map as $v) {
                        if (is_string($v) && trim($v) !== '') {
                                $daryaRoleValues[] = trim($v);
                        }
                }
                $daryaRoleValues = array_values(array_unique($daryaRoleValues));

                $counts = Queue::counts();
                $logs = dbr_log_recent(20);

                global $wp_roles;

                // اعلان‌های پس از عملیات
                $noticeKey = isset($_GET['dbr_notice']) ? sanitize_key(wp_unslash($_GET['dbr_notice'])) : '';
                $noticeText = '';
                if ('secret' === $noticeKey) {
                        $noticeText = 'کلید امنیتی جدید تولید شد. آن را در نرم‌افزار ویندوزی داریا به‌روزرسانی کنید.';
                } elseif ('cleared' === $noticeKey) {
                        $noticeText = 'صف همگام‌سازی خالی شد.';
                } elseif ('rolemap' === $noticeKey) {
                        $noticeText = 'نگاشت نقش‌ها در داریا ذخیره شد.';
                } elseif ('rolemap_err' === $noticeKey) {
                        $noticeText = 'ذخیره نگاشت نقش‌ها ناموفق بود. اتصال به داریا را بررسی کنید.';
                } elseif ('flushed' === $noticeKey) {
                        $sent = absint(isset($_GET['sent']) ? $_GET['sent'] : 0);
                        $failed = absint(isset($_GET['failed']) ? $_GET['failed'] : 0);
                        $noticeText = sprintf('صف اجرا شد — %d رویداد ارسال شد، %d خطا.', $sent, $failed);
                }
                ?>
                <div class="wrap dbr-wrap" dir="rtl" style="direction:rtl;">
                        <h1>پل داریا <span class="dbr-pill info">نسخه <?php echo esc_html(DBR_VERSION); ?></span></h1>

                        <?php if ($noticeText !== '') : ?>
                                <div class="notice notice-success is-dismissible"><p><?php echo esc_html($noticeText); ?></p></div>
                        <?php endif; ?>

                        <form method="post" action="options.php">
                                <?php settings_fields('darya_bridge'); ?>

                                <h2 class="title">اتصال به داریا</h2>
                                <table class="form-table" role="presentation">
                                        <tr>
                                                <th scope="row"><label for="dbr-darya-url">آدرس داریا</label></th>
                                                <td>
                                                        <input id="dbr-darya-url" type="url" class="regular-text ltr" name="darya_bridge_settings[darya_url]" value="<?php echo esc_attr($opts['darya_url']); ?>" placeholder="https://darya.example.com">
                                                        <p class="description">آدرس پایه نصب نرم‌افزار داریا؛ مثل <code dir="ltr">https://darya.example.com</code></p>
                                                </td>
                                        </tr>
                                        <tr>
                                                <th scope="row"><label for="dbr-api-key">کلید API</label></th>
                                                <td>
                                                        <input id="dbr-api-key" type="password" autocomplete="new-password" class="regular-text ltr" name="darya_bridge_settings[api_key]" value="<?php echo esc_attr($opts['api_key']); ?>">
                                                        <p class="description">کلید ساخته‌شده در داریا (شبیه <code dir="ltr">dk_...</code>) — عیناً ذخیره می‌شود.</p>
                                                </td>
                                        </tr>
                                        <tr>
                                                <th scope="row"><label for="dbr-secret">کلید امنیتی (Secret)</label></th>
                                                <td>
                                                        <input id="dbr-secret" type="text" readonly class="regular-text ltr" value="<?php echo esc_attr($opts['secret']); ?>" onclick="this.select();">
                                                        <a class="button" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=dbr_regenerate_secret'), 'dbr_admin')); ?>">تولید مجدد</a>
                                                        <p class="description">این مقدار را در نرم‌افزار ویندوزی داریا وارد کنید تا به REST این فروشگاه دسترسی داشته باشد.</p>
                                                </td>
                                        </tr>
                                </table>

                                <h2 class="title">چه چیزی همگام شود؟</h2>
                                <table class="form-table" role="presentation">
                                        <tr>
                                                <th scope="row">همگام‌سازی</th>
                                                <td>
                                                        <label><input type="checkbox" name="darya_bridge_settings[sync_products]" value="1" <?php checked($opts['sync_products'], 1); ?>> محصولات</label><br>
                                                        <label><input type="checkbox" name="darya_bridge_settings[sync_orders]" value="1" <?php checked($opts['sync_orders'], 1); ?>> سفارش‌ها</label><br>
                                                        <label><input type="checkbox" name="darya_bridge_settings[sync_customers]" value="1" <?php checked($opts['sync_customers'], 1); ?>> مشتریان</label><br>
                                                        <label><input type="checkbox" name="darya_bridge_settings[sync_roles]" value="1" <?php checked($opts['sync_roles'], 1); ?>> نقش‌های کاربران (مهم)</label><br>
                                                        <label><input type="checkbox" name="darya_bridge_settings[sync_carts]" value="1" <?php checked($opts['sync_carts'], 1); ?>> سبد رهاشده</label>
                                                        <p class="description">نقش‌های کاربران با هر تغییر (ثبت‌نام، ویرایش پروفایل، تغییر نقش) بلافاصله به داریا ارسال می‌شود.</p>
                                                </td>
                                        </tr>
                                        <tr>
                                                <th scope="row"><label for="dbr-abandon">مهلت رهاشدن سبد (دقیقه)</label></th>
                                                <td>
                                                        <input id="dbr-abandon" type="number" min="1" max="1440" class="small-text ltr" name="darya_bridge_settings[cart_abandon_minutes]" value="<?php echo esc_attr($opts['cart_abandon_minutes']); ?>">
                                                        <p class="description">سبدهایی که در این مدت فعالیتی نداشته باشند «رهاشده» علامت می‌خورند و به داریا ارسال می‌شوند.</p>
                                                </td>
                                        </tr>
                                        <tr>
                                                <th scope="row"><label for="dbr-interval">فاصله اجرای صف (ثانیه)</label></th>
                                                <td>
                                                        <input id="dbr-interval" type="number" min="60" step="30" class="small-text ltr" name="darya_bridge_settings[wp_cron_interval]" value="<?php echo esc_attr($opts['wp_cron_interval']); ?>">
                                                        <p class="description">بازه زمان‌بندی WP-Cron برای ارسال صف (پیش‌فرض ۳۰۰ ثانیه).</p>
                                                </td>
                                        </tr>
                                </table>

                                <?php submit_button('ذخیره تنظیمات'); ?>
                        </form>

                        <hr>

                        <h2 class="title">وضعیت اتصال</h2>
                        <p>
                                <button type="button" class="button button-secondary" id="dbr-test-btn" data-nonce="<?php echo esc_attr(wp_create_nonce('dbr_admin')); ?>">بررسی اتصال</button>
                                <span class="dbr-muted">هم‌زمان وضعیت افزونه در وردپرس و پاسخ‌گویی سرویس داریا (v1/ping) بررسی می‌شود.</span>
                        </p>
                        <div id="dbr-test-result">
                                <span class="dbr-muted">برای بررسی، دکمه بالا را بزنید.</span>
                        </div>
                        <p class="description">آدرس REST برای نرم‌افزار ویندوزی: <code dir="ltr"><?php echo esc_html(home_url('/wp-json/darya-bridge/v1')); ?></code> (به‌همراه <code dir="ltr">?secret=...</code>)</p>

                        <hr>

                        <h2 class="title">نقش‌های کاربران وردپرس</h2>
                        <p>هر نقش وردپرس را می‌توانید به یک نقش داریا (slug) نگاشت کنید؛ نقش‌های موجود از نقشه فعلی داریا بارگذاری شده‌اند.</p>
                        <?php if (!Settings::instance()->configured()) : ?>
                                <p><span class="dbr-pill warn">ابتدا آدرس و کلید API داریا را ذخیره کنید تا نقشه نقش‌ها از داریا خوانده شود.</span></p>
                        <?php endif; ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                <input type="hidden" name="action" value="dbr_save_role_map">
                                <?php wp_nonce_field('dbr_admin'); ?>
                                <table class="widefat striped dbr-roles-table" style="max-width:640px;">
                                        <thead>
                                                <tr><th>نقش وردپرس</th><th>نقش داریا (slug)</th></tr>
                                        </thead>
                                        <tbody>
                                                <?php
                                                $allRoles = ($wp_roles instanceof WP_Roles) ? $wp_roles->get_names() : array();
                                                foreach ((array) $allRoles as $roleSlug => $roleName) :
                                                        $current = isset($map[$roleSlug]) && is_string($map[$roleSlug]) ? $map[$roleSlug] : '';
                                                        ?>
                                                        <tr>
                                                                <td><strong><?php echo esc_html($roleName); ?></strong> <span class="dbr-muted" dir="ltr">(<?php echo esc_html($roleSlug); ?>)</span></td>
                                                                <td>
                                                                        <input type="text" list="dbr-darya-roles" dir="ltr" name="darya_role_map[<?php echo esc_attr($roleSlug); ?>]" value="<?php echo esc_attr($current); ?>" placeholder="— بدون نگاشت —">
                                                                </td>
                                                        </tr>
                                                <?php endforeach; ?>
                                        </tbody>
                                </table>
                                <datalist id="dbr-darya-roles">
                                        <?php foreach ($daryaRoleValues as $val) : ?>
                                                <option value="<?php echo esc_attr($val); ?>"></option>
                                        <?php endforeach; ?>
                                </datalist>
                                <p>
                                        <button type="submit" class="button button-primary">ذخیره نگاشت نقش‌ها</button>
                                        <span class="dbr-muted">ذخیره از طریق داریا (POST v1/role-map) انجام می‌شود.</span>
                                </p>
                        </form>
                        <p class="description">با تغییر نقش هر کاربر، اطلاعات و نقش‌های او بلافاصله به داریا همگام می‌شود (v1/users/sync)؛ در صورت وجود نگاشت، کاربر داریا ساخته/بروزرسانی می‌گردد.</p>

                        <hr>

                        <h2 class="title">صف همگام‌سازی</h2>
                        <p>
                                <span class="dbr-pill <?php echo $counts['total'] > 0 ? 'warn' : 'ok'; ?>">کل صف: <?php echo esc_html((string) $counts['total']); ?></span>
                                <span class="dbr-pill <?php echo $counts['failed'] > 0 ? 'err' : 'ok'; ?>">دارای خطا: <?php echo esc_html((string) $counts['failed']); ?></span>
                                <?php foreach ((array) $counts['by_type'] as $type => $count) : ?>
                                        <span class="dbr-pill info"><?php echo esc_html((string) $type); ?>: <?php echo esc_html((string) $count); ?></span>
                                <?php endforeach; ?>
                        </p>
                        <p class="dbr-muted">
                                اجراهای بعدی — صف: <?php echo esc_html(self::nextRunText(wp_next_scheduled('darya_bridge_flush_queue'))); ?> |
                                پایش سبد: <?php echo esc_html(self::nextRunText(wp_next_scheduled('darya_bridge_carts_sweep'))); ?> |
                                نقش‌های کامل: <?php echo esc_html(self::nextRunText(wp_next_scheduled('darya_bridge_roles_full'))); ?>
                        </p>
                        <div>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="dbr-inline-form">
                                        <input type="hidden" name="action" value="dbr_flush_queue">
                                        <?php wp_nonce_field('dbr_admin'); ?>
                                        <button type="submit" class="button button-primary">اجرای فوری صف</button>
                                </form>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="dbr-inline-form" id="dbr-clear-queue-form">
                                        <input type="hidden" name="action" value="dbr_clear_queue">
                                        <?php wp_nonce_field('dbr_admin'); ?>
                                        <button type="submit" class="button">خالی کردن صف</button>
                                </form>
                        </div>

                        <h3>آخرین ۲۰ لاگ</h3>
                        <table class="widefat striped dbr-log-table" style="max-width:1100px;">
                                <thead>
                                        <tr>
                                                <th>زمان</th><th>جهت</th><th>موجودیت</th><th>مرجع</th><th>وضعیت</th><th>پیام</th>
                                        </tr>
                                </thead>
                                <tbody>
                                        <?php if (empty($logs)) : ?>
                                                <tr><td colspan="6">لاگی ثبت نشده است.</td></tr>
                                        <?php else : ?>
                                                <?php foreach ($logs as $log) : ?>
                                                        <tr>
                                                                <td class="col-time" dir="ltr"><?php echo esc_html((string) $log['created_at']); ?></td>
                                                                <td><?php echo esc_html((string) $log['direction']); ?></td>
                                                                <td><?php echo esc_html((string) $log['entity']); ?></td>
                                                                <td dir="ltr"><?php echo esc_html((string) $log['ref']); ?></td>
                                                                <td>
                                                                        <span class="dbr-pill <?php echo ('ok' === $log['status']) ? 'ok' : (('error' === $log['status']) ? 'err' : 'warn'); ?>">
                                                                                <?php echo esc_html((string) $log['status']); ?>
                                                                        </span>
                                                                </td>
                                                                <td><?php echo esc_html(mb_substr((string) $log['message'], 0, 160)); ?></td>
                                                        </tr>
                                                <?php endforeach; ?>
                                        <?php endif; ?>
                                </tbody>
                        </table>
                </div>
                <?php
        }

        /** نمایش زمان‌بندی بعدی cron */
        private static function nextRunText($timestamp) {
                if (!$timestamp) {
                        return 'زمان‌بندی نشده';
                }
                $diff = (int) $timestamp - time();
                if ($diff <= 0) {
                        return 'همین حالا';
                }
                return date_i18n('H:i', $timestamp) . ' (حدود ' . human_time_diff(time(), $timestamp) . ' بعد)';
        }

        /* ---------------- عملیات admin-post ---------------- */

        private static function back($args = array()) {
                wp_safe_redirect(add_query_arg(array_merge(array('page' => self::PAGE_SLUG), $args), admin_url('admin.php')));
                exit;
        }

        private static function checkAccess() {
                if (!current_user_can('manage_woocommerce')) {
                        wp_die('شما دسترسی لازم برای این عملیات را ندارید.');
                }
                check_admin_referer('dbr_admin');
        }

        public static function handleRegenerateSecret() {
                self::checkAccess();
                Settings::instance()->regenerateSecret();
                self::back(array('dbr_notice' => 'secret'));
        }

        public static function handleFlushQueue() {
                self::checkAccess();
                $result = Queue::flush(50);
                self::back(array(
                        'dbr_notice' => 'flushed',
                        'sent'       => isset($result['sent']) ? (int) $result['sent'] : 0,
                        'failed'     => isset($result['failed']) ? (int) $result['failed'] : 0,
                ));
        }

        public static function handleClearQueue() {
                self::checkAccess();
                Queue::clear();
                self::back(array('dbr_notice' => 'cleared'));
        }

        public static function handleSaveRoleMap() {
                self::checkAccess();

                $posted = (isset($_POST['darya_role_map']) && is_array($_POST['darya_role_map'])) ? wp_unslash($_POST['darya_role_map']) : array();

                // نقشه فعلی از داریا خوانده و ادغام می‌شود
                $current = array();
                $fetched = ApiClient::instance()->request('v1/role-map', array(), 'GET');
                if (ApiClient::ok($fetched) && isset($fetched['data']['map']) && is_array($fetched['data']['map'])) {
                        $current = $fetched['data']['map'];
                }

                foreach ((array) $posted as $wpRole => $daryaRole) {
                        $wpRole = sanitize_key((string) $wpRole);
                        if ($wpRole === '') {
                                continue;
                        }
                        $daryaRole = trim(sanitize_text_field((string) $daryaRole));
                        $current[$wpRole] = ($daryaRole === '') ? null : $daryaRole;
                }

                $saved = ApiClient::instance()->request('v1/role-map', array('map' => $current));
                self::back(array('dbr_notice' => ApiClient::ok($saved) ? 'rolemap' : 'rolemap_err'));
        }

        /* ---------------- AJAX تست اتصال ---------------- */

        public static function ajaxTestConnection() {
                check_ajax_referer('dbr_admin', 'nonce');
                if (!current_user_can('manage_woocommerce')) {
                        wp_send_json_error(array('message' => 'دسترسی ندارید'), 403);
                }

                // ۱) فراخوانی داخلی REST خود افزونه (/status)
                $internal = new WP_REST_Request('GET', '/darya-bridge/v1/status');
                $internal->set_param('secret', (string) Settings::instance()->get('secret'));
                $response = rest_do_request($internal);
                $wpStatus = rest_get_server()->response_to_data($response, false);

                // ۲) تست سمت سرور به داریا (v1/ping)
                $ping = ApiClient::instance()->request('v1/ping', array(), 'GET');
                $darya = is_wp_error($ping)
                        ? array('ok' => false, 'error' => array('code' => $ping->get_error_code(), 'message' => $ping->get_error_message()))
                        : $ping;

                wp_send_json(array(
                        'ok'    => true,
                        'wp'    => is_array($wpStatus) ? $wpStatus : array('ok' => false),
                        'darya' => $darya,
                ));
        }

        /* ---------------- جاوااسکریپت درون‌خطی ---------------- */

        private static function inlineJs() {
                return <<<'JS'
(function () {
        'use strict';
        function pill(cls, text) {
                return '<span class="dbr-pill ' + cls + '">' + text + '</span> ';
        }
        function renderWp(d) {
                if (!d || !d.ok) {
                        return pill('err', 'وردپرس: خطای داخلی REST');
                }
                var q = (d.queue && typeof d.queue.total !== 'undefined') ? d.queue.total : '?';
                return pill('ok', 'وردپرس: متصل — نسخه ' + d.version + ' — صف: ' + q);
        }
        function renderDarya(d) {
                if (d && d.ok) {
                        var v = (d.data && d.data.version) ? d.data.version : '?';
                        return pill('ok', 'داریا: پاسخ داد — نسخه ' + v);
                }
                var msg = (d && d.error && d.error.message) ? d.error.message : 'اتصال برقرار نشد';
                return pill('err', 'داریا: ' + msg);
        }

        var btn = document.getElementById('dbr-test-btn');
        var out = document.getElementById('dbr-test-result');
        if (btn && out) {
                btn.addEventListener('click', function (e) {
                        e.preventDefault();
                        btn.disabled = true;
                        out.innerHTML = '<span class="dbr-pill warn">در حال بررسی…</span>';
                        var fd = new FormData();
                        fd.append('action', 'dbr_test_connection');
                        fd.append('nonce', btn.getAttribute('data-nonce'));
                        var url = (typeof window.ajaxurl === 'string') ? window.ajaxurl : '/wp-admin/admin-ajax.php';
                        fetch(url, { method: 'POST', credentials: 'same-origin', body: fd })
                                .then(function (r) { return r.json(); })
                                .then(function (d) {
                                        out.innerHTML = renderWp(d.wp) + renderDarya(d.darya);
                                        btn.disabled = false;
                                })
                                .catch(function () {
                                        out.innerHTML = pill('err', 'خطای شبکه در ارتباط با مدیریت وردپرس');
                                        btn.disabled = false;
                                });
                });
        }

        var clr = document.getElementById('dbr-clear-queue-form');
        if (clr) {
                clr.addEventListener('submit', function (e) {
                        if (!window.confirm('همه ردیف\u200cهای صف حذف شوند؟ این عمل بازگشت\u200cپذیر نیست.')) {
                                e.preventDefault();
                        }
                });
        }
})();
JS;
        }
}

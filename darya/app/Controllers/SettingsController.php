<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;

/** تنظیمات — settings.view / settings.edit / sync.manage */
class SettingsController extends Controller
{
    /** کلیدهای مجاز فرم تنظیمات (ناشناخته‌ها نادیده گرفته می‌شوند) */
    private const SETTING_KEYS = [
        'company_name', 'company_phone', 'company_address', 'company_economic_code',
        'currency', 'tax_rate',
        'invoice_prefix', 'purchase_prefix', 'trx_prefix', 'voucher_prefix',
    ];

    public function indexAction(): void
    {
        $this->requirePerm('settings.view');

        $values = [];
        foreach (self::SETTING_KEYS as $k) {
            $values[$k] = (string) setting($k, '');
        }
        $values['sync_enabled'] = (string) setting('sync_enabled', '1');

        $this->view('settings/index', [
            'title'  => 'تنظیمات',
            'values' => $values,
        ]);
    }

    public function storeAction(): void
    {
        $this->requirePerm('settings.edit');

        foreach (self::SETTING_KEYS as $k) {
            $v = trim((string) $this->input($k, ''));
            if ($k === 'tax_rate') {
                $v = (string) round(max(0, min(100, $this->parseNum($v))), 2);
            } elseif (str_ends_with($k, '_prefix')) {
                $v = preg_replace('/[^a-zA-Z0-9_\-]/', '', $v) ?? '';
            }
            Database::q('UPDATE settings SET `value` = ? WHERE `key` = ?', [$v, $k]);
        }
        // چک‌باکس همگام‌سازی: اگر تیک نخورد مقدار ۰
        Database::q('UPDATE settings SET `value` = ? WHERE `key` = ?', [$this->input('sync_enabled') ? '1' : '0', 'sync_enabled']);

        flash_set('success', 'تنظیمات ذخیره شد.');
        $this->redirect('settings');
    }

    /* ---------------- کلیدهای API ---------------- */

    public function apiKeysAction(): void
    {
        $this->requirePerm('sync.manage');

        $rows = Database::all('SELECT * FROM api_keys ORDER BY id DESC');

        $this->view('settings/api-keys', [
            'title' => 'کلیدهای API',
            'rows'  => $rows,
        ]);
    }

    /** مسیر: settings/store-api-key */
    public function storeApiKeyAction(): void
    {
        $this->requirePerm('sync.manage');

        $name = trim((string) $this->input('name', ''));
        if ($name === '') {
            flash_set('error', 'نام کلید الزامی است.');
            $this->redirect('settings/api-keys');
        }

        $plain = 'dk_' . bin2hex(random_bytes(24));
        Database::insert('api_keys', [
            'name' => $name,
            'key_prefix' => substr($plain, 0, 12),
            'key_hash' => hash('sha256', $plain),
            'scopes' => '*',
            'is_active' => 1,
        ]);

        // کلید فقط همین یک بار (بعد از رندر صفحه از سشن پاک می‌شود)
        $_SESSION['new_api_key'] = $plain;
        flash_set('success', 'کلید API ساخته شد. کلید را همین حالا کپی کنید؛ دوباره نمایش داده نمی‌شود.');
        $this->redirect('settings/api-keys');
    }

    /** مسیر: settings/toggle-api-key/{id} */
    public function toggleApiKeyAction(array $args): void
    {
        $this->requirePerm('sync.manage');
        $id = (int) ($args[0] ?? 0);
        $row = Database::one('SELECT * FROM api_keys WHERE id = ?', [$id]);
        if ($row) {
            Database::q('UPDATE api_keys SET is_active = ? WHERE id = ?', [(int) $row['is_active'] === 1 ? 0 : 1, $id]);
            flash_set('success', (int) $row['is_active'] === 1 ? 'کلید غیرفعال شد.' : 'کلید فعال شد.');
        }
        $this->redirect('settings/api-keys');
    }

    /** مسیر: settings/delete-api-key/{id} */
    public function deleteApiKeyAction(array $args): void
    {
        $this->requirePerm('sync.manage');
        $id = (int) ($args[0] ?? 0);
        Database::delete('api_keys', 'id = ?', [$id]);
        flash_set('success', 'کلید حذف شد.');
        $this->redirect('settings/api-keys');
    }

    /* ---------------- نقشه نقش‌ها (وردپرس ← داریا) ---------------- */

    public function roleMapAction(): void
    {
        $this->requirePerm('sync.manage');

        $map = json_decode((string) setting('role_map', '{}'), true) ?: [];
        if (!is_array($map)) $map = [];

        // نقش‌های ثابت وردپرس + نقش‌های سفارشی موجود در JSON
        $wpRoles = [
            'administrator' => 'مدیر کل (Administrator)',
            'shop_manager'  => 'مدیر فروشگاه (Shop Manager)',
            'editor'        => 'ویراستار (Editor)',
            'author'        => 'نویسنده (Author)',
            'contributor'   => 'همکار (Contributor)',
            'subscriber'    => 'مشترک (Subscriber)',
            'customer'      => 'مشتری (Customer)',
        ];
        foreach (array_keys($map) as $k) {
            if (!isset($wpRoles[(string) $k]) && is_string($k) && $k !== '') {
                $wpRoles[$k] = $k . ' (سفارشی)';
            }
        }

        $daryaRoles = Database::all('SELECT id, name, slug FROM roles ORDER BY id');

        $this->view('settings/role-map', [
            'title'      => 'نگاشت نقش‌های وردپرس',
            'wpRoles'    => $wpRoles,
            'map'        => $map,
            'daryaRoles' => $daryaRoles,
        ]);
    }

    /** مسیر: settings/save-role-map */
    public function saveRoleMapAction(): void
    {
        $this->requirePerm('sync.manage');

        $map = json_decode((string) setting('role_map', '{}'), true) ?: [];
        if (!is_array($map)) $map = [];

        $wpRoles = ['administrator', 'shop_manager', 'editor', 'author', 'contributor', 'subscriber', 'customer'];
        $wpRoles = array_merge($wpRoles, array_keys($map)); // نقش‌های سفارشی قبلی حفظ شوند
        $daryaSlugs = array_column(Database::all('SELECT slug FROM roles'), 'slug');

        $new = [];
        foreach ($wpRoles as $wp) {
            $val = trim((string) ($this->input('map_' . $wp, '') ?? ''));
            if ($val === '') {
                $new[$wp] = null;
            } elseif (in_array($val, $daryaSlugs, true)) {
                $new[$wp] = $val; // فقط نقش‌های موجود داریا
            }
        }
        ksort($new);

        Database::q(
            'UPDATE settings SET `value` = ? WHERE `key` = ?',
            [json_encode($new, JSON_UNESCAPED_UNICODE), 'role_map']
        );
        \App\Services\SyncService::log('map', 'role_map', 'update', 'save', 'ok', 'نقشه نقش‌ها از تنظیمات ذخیره شد');
        flash_set('success', 'نقشه نقش‌ها ذخیره شد.');
        $this->redirect('settings/role-map');
    }

    /* ----------------helpers---------------- */

    private function parseNum(string $v): float
    {
        $v = str_replace([',', '،', '٬', ' '], '', fa_num_reverse(trim($v)));
        return (float) ($v === '' ? '0' : $v);
    }
}

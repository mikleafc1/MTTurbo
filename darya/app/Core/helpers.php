<?php
/**
 * توابع کمکی سراسری داریا + تبدیل تاریخ شمسی
 */
declare(strict_types=1);

function config(?string $key = null, $default = null)
{
    static $flat = null;
    if ($flat === null) {
        $flat = [];
        foreach (($GLOBALS['darya_config'] ?? []) as $group => $values) {
            if (is_array($values)) {
                foreach ($values as $k => $v) {
                    $flat["$group.$k"] = $v;
                }
            } else {
                $flat[$group] = $values;
            }
        }
    }
    if ($key === null) return $flat;
    return $flat[$key] ?? $default;
}

function url(string $path = ''): string
{
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
    if ($base === '.' || $base === '/') $base = '';
    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/')) . '?v=1.0.0';
}

function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function fa_num($value): string
{
    return strtr((string) $value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
}

function money($n, bool $fa = true): string
{
    $s = number_format((float) $n, ((float) $n == (int) $n) ? 0 : 2, '.', ',');
    return $fa ? fa_num($s) : $s;
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function today(): string
{
    return date('Y-m-d');
}

/* ================= تاریخ شمسی ================= */

function gregorian_to_jalali(int $gy, int $gm, int $gd): array
{
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $jy = ($gy <= 1600) ? 0 : 979;
    $gy -= ($gy <= 1600) ? 621 : 1600;
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) - 80 + $gd + $g_d_m[$gm - 1];
    $jy += 33 * intdiv($days, 12053);
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    $jm = ($days < 186) ? 1 + intdiv($days, 31) : 7 + intdiv($days - 186, 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    return [$jy, $jm, $jd];
}

function jalali_to_gregorian(int $jy, int $jm, int $jd): array
{
    $jy += 1595;
    $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd
        + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
    $gy = 400 * intdiv($days, 146097);
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * intdiv(--$days, 36524);
        $days %= 36524;
        if ($days >= 365) $days++;
    }
    $gy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $gy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    $gd = $days + 1;
    $ml = [0, 31, (($gy % 4 == 0 && $gy % 100 != 0) || ($gy % 400 == 0)) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
    $gm = 0;
    for ($gm = 0; $gm < 13 && $gd > $ml[$gm]; $gm++) {
        $gd -= $ml[$gm];
    }
    return [$gy, $gm, $gd];
}

function jdate(?string $date, string $format = 'Y/m/d'): string
{
    if (!$date) return '';
    $ts = strtotime($date);
    if ($ts === false) return '';
    [$jy, $jm, $jd] = gregorian_to_jalali((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts));
    $months = ['', 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    $out = str_replace(
        ['Y', 'm', 'd', 'M', 'H', 'i'],
        [str_pad((string) $jy, 4, '0', STR_PAD_LEFT), str_pad((string) $jm, 2, '0', STR_PAD_LEFT), str_pad((string) $jd, 2, '0', STR_PAD_LEFT), $months[$jm], date('H', $ts), date('i', $ts)],
        $format
    );
    return fa_num($out);
}

/** ورودی کاربر شمسی (مثل 1403/05/12) → تاریخ میلادی برای ذخیره */
function jinput_to_gregorian(?string $input): ?string
{
    $input = str_replace(['-','.','،', fa_num('/')], '/', trim((string) $input));
    $input = fa_num_reverse($input);
    if (!preg_match('#^(\d{4})/(\d{1,2})/(\d{1,2})$#', $input, $m)) return null;
    [$gy, $gm, $gd] = jalali_to_gregorian((int) $m[1], (int) $m[2], (int) $m[3]);
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}

function fa_num_reverse(string $s): string
{
    return strtr($s, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9', '٫' => '.']);
}

function jmonth_select(string $name, int $selected = 1): string
{
    $months = [1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان', 9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'];
    $html = "<select name=\"" . e($name) . "\" class=\"form-input\">";
    foreach ($months as $i => $label) {
        $html .= "<option value=\"$i\"" . ($i === $selected ? ' selected' : '') . ">$label</option>";
    }
    return $html . '</select>';
}

function jyear_options(int $selected): string
{
    $y = (int) jdate(today(), 'Y');
    $html = '';
    for ($i = $y; $i >= $y - 10; $i--) {
        $html .= '<option value="' . $i . '"' . ($i === $selected ? ' selected' : '') . '>' . fa_num($i) . '</option>';
    }
    return $html;
}

/* ================= فلش پیام / تنظیمات ================= */

function flash_set(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get(): array
{
    return $_SESSION['_flash'] ?? [];
}

function flash_consume(): void
{
    unset($_SESSION['_flash']);
}

function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (\App\Core\Database::all('SELECT `key`, `value` FROM settings') as $row) {
                $cache[$row['key']] = $row['value'];
            }
        } catch (\Throwable $e) {
        }
    }
    return $cache[$key] ?? $default;
}

function setting_refresh(): void
{
    // force reload next call
    static $ref = null;
}

function is_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function csrf_field(): string
{
    return \App\Core\Csrf::field();
}

function can(string $perm): bool
{
    return \App\Core\Auth::can($perm);
}

function status_label(string $status): string
{
    $map = [
        'draft' => ['پیش‌نویس', 'badge-gray'],
        'pending' => ['در انتظار تأیید', 'badge-amber'],
        'unpaid' => ['پرداخت نشده', 'badge-red'],
        'partial' => ['پرداخت جزئی', 'badge-amber'],
        'paid' => ['پرداخت شده', 'badge-green'],
        'canceled' => ['لغو شده', 'badge-gray'],
        'in_hand' => ['در جریان', 'badge-amber'],
        'deposited' => ['وصول بانک', 'badge-amber'],
        'cleared' => ['وصول شد', 'badge-green'],
        'bounced' => ['برگشتی', 'badge-red'],
        'abandoned' => ['رهاشده', 'badge-red'],
        'converted' => ['تبدیل به سفارش', 'badge-green'],
        'recovered' => ['بازیابی شده', 'badge-green'],
        'open' => ['فعال', 'badge-amber'],
        'done' => ['انجام شد', 'badge-green'],
        'failed' => ['ناموفق', 'badge-red'],
        'active' => ['فعال', 'badge-green'],
        'inactive' => ['غیرفعال', 'badge-gray'],
    ];
    [$label, $class] = $map[$status] ?? [$status, 'badge-gray'];
    return '<span class="badge ' . $class . '">' . e($label) . '</span>';
}

function invoice_type_label(string $t): string
{
    return match ($t) {
        'sale' => 'فروش', 'purchase' => 'خرید', 'return_sale' => 'برگشتی فروش', 'return_purchase' => 'برگشتی خرید', default => $t,
    };
}

/** آیکون‌های خطی سبک lucide (بدون کتابخانه خارجی) */
function icon_svg(string $name, int $size = 18): string
{
    $paths = [
        'home'   => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 10v10h14V10"/>',
        'cart'   => '<circle cx="9" cy="20" r="1.6"/><circle cx="17" cy="20" r="1.6"/><path d="M3 4h2l2.6 11.6a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.8L20.5 8H6"/>',
        'box'    => '<path d="M12 3 3 7.5v9L12 21l9-4.5v-9L12 3z"/><path d="M3 7.5 12 12l9-4.5M12 12v9"/>',
        'book'   => '<path d="M4 4h13a3 3 0 0 1 3 3v13H7a3 3 0 0 1-3-3V4z"/><path d="M4 17a3 3 0 0 1 3-3h13"/>',
        'wallet' => '<path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7z"/><path d="M16 12h5v3h-5a1.5 1.5 0 0 1 0-3z"/>',
        'users'  => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M16 5a3 3 0 0 1 0 6M17.5 14.5a5.5 5.5 0 0 1 3 5.5"/>',
        'badge'  => '<circle cx="12" cy="9" r="5"/><path d="M8.5 13.5 7 21l5-2.5L17 21l-1.5-7.5"/>',
        'chart'  => '<path d="M4 20V10M10 20V4M16 20v-7M21 20H3"/>',
        'sheet'  => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M4 9h16M4 15h16M10 3v18M16 3v18"/>',
        'sync'   => '<path d="M20 11a8 8 0 0 0-14-4.5M4 13a8 8 0 0 0 14 4.5"/><path d="M4 4v4h4M20 20v-4h-4"/>',
        'shield' => '<path d="M12 3 5 6v5c0 5 3.2 8.4 7 10 3.8-1.6 7-5 7-10V6l-7-3z"/>',
        'gear'   => '<circle cx="12" cy="12" r="3.2"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1"/>',
    ];
    $p = $paths[$name] ?? $paths['box'];
    return '<svg class="i" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

<?php
/**
 * داریا موبوآردر — اپ سفارش‌گیری مبلغان (PWA)
 * ورودی مستقل با نشست جدا (MOBOSESSID) — مسیرها با ?p=login|products|cart|submit|success|logout
 */
declare(strict_types=1);

use App\Core\Database;
use App\Core\Csrf;
use App\Services\InventoryService;

define('BASE_PATH', dirname(__DIR__));

if (!file_exists(BASE_PATH . '/config/config.php')) {
    http_response_code(500);
    die('<!doctype html><html dir="rtl" lang="fa"><meta charset="utf-8"><body style="font-family:Tahoma;text-align:center;padding:60px"><h2>تنظیمات یافت نشد</h2><p>ابتدا سامانه داریا را نصب کنید.</p></body></html>');
}

$config = require BASE_PATH . '/config/config.php';
$GLOBALS['darya_config'] = $config;

spl_autoload_register(function (string $class): void {
    $file = BASE_PATH . '/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require_once BASE_PATH . '/app/Core/helpers.php';

date_default_timezone_set($config['timezone'] ?? 'Asia/Tehran');
mb_internal_encoding('UTF-8');

session_name('MOBOSESSID');
session_start();

error_reporting(E_ALL);
ini_set('display_errors', (!empty($config['debug'])) ? '1' : '0');
ini_set('log_errors', '1');

set_exception_handler(function (Throwable $e) {
    error_log('[DARYA MOBO] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    echo '<!doctype html><html dir="rtl" lang="fa"><head><meta charset="utf-8"><title>خطا</title></head>'
        . '<body style="font-family:Tahoma;text-align:center;padding:60px">'
        . '<h2 style="color:#b91c1c">خطای سیستمی</h2><p>مشکلی رخ داده است. لطفاً دوباره تلاش کنید.</p></body></html>';
});

/* ============================ توابع کمکی نمای موبو ============================ */

function mobo_head(string $title): void
{
    echo '<!doctype html><html dir="rtl" lang="fa"><head>'
        . '<meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">'
        . '<meta name="theme-color" content="#0f766e">'
        . '<title>' . e($title) . ' | داریا موبوآردر</title>'
        . '<link rel="manifest" href="manifest.json">'
        . '<link rel="stylesheet" href="style.css">'
        . '</head><body>';
}

function mobo_foot(): void
{
    echo '<script>if ("serviceWorker" in navigator) { navigator.serviceWorker.register("sw.js").catch(function(){}); }</script>'
        . '</body></html>';
}

function mobo_topbar(?array $promoter, int $cartCount): void
{
    echo '<header class="topbar">'
        . '<div class="brand"><span class="logo">د</span><span class="brand-text">موبوآردر</span></div>';
    if ($promoter) {
        echo '<div class="topbar-user">'
            . '<a class="cart-link" href="?p=cart" aria-label="سبد سفارش">🛒' . ($cartCount > 0 ? '<b class="cart-count">' . fa_num($cartCount) . '</b>' : '') . '</a>'
            . '<span class="user-name">' . e($promoter['full_name']) . '</span>'
            . '<a class="logout" href="?p=logout">خروج</a>'
            . '</div>';
    }
    echo '</header>';
}

function mobo_flashes(): void
{
    foreach (flash_get() as $f) {
        echo '<div class="alert alert-' . e($f['type']) . '">' . e($f['message']) . '</div>';
    }
    flash_consume();
}

/** تبدیل عدد فارسی/با کاما به int */
function mobo_num(mixed $v): int
{
    return (int) fa_num_reverse(str_replace(',', '', (string) $v));
}

/* ============================ مسیریابی ============================ */

$p = (string) ($_GET['p'] ?? 'login');
$p = preg_replace('#[^a-z_]#', '', $p) ?: 'login';

/* مبلغ جاری (بررسی فعال بودن در هر درخواست) */
$promoter = null;
if (!empty($_SESSION['mobo_pid'])) {
    $promoter = Database::one('SELECT * FROM promoters WHERE id = ? AND is_active = 1', [(int) $_SESSION['mobo_pid']]);
    if (!$promoter) {
        unset($_SESSION['mobo_pid'], $_SESSION['mobo_cart']);
    }
}

$needsAuth = in_array($p, ['products', 'cart', 'submit', 'success'], true);
if ($needsAuth && !$promoter) {
    header('Location: ?p=login');
    exit;
}

/* خروج */
if ($p === 'logout') {
    unset($_SESSION['mobo_pid'], $_SESSION['mobo_cart']);
    header('Location: ?p=login');
    exit;
}

/* ---------- پردازش POSTها (با CSRF) ---------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    Csrf::verify();

    if ($p === 'login') {
        $mobile = trim((string) ($_POST['mobile'] ?? ''));
        $pin = trim((string) ($_POST['pin'] ?? ''));

        $_SESSION['mobo_attempts'] = (int) ($_SESSION['mobo_attempts'] ?? 0);
        $_SESSION['mobo_last_attempt'] = (int) ($_SESSION['mobo_last_attempt'] ?? 0);
        if ($_SESSION['mobo_attempts'] >= 5 && (time() - $_SESSION['mobo_last_attempt']) < 300) {
            flash_set('error', 'تلاش‌های ناموفق زیاد است؛ چند دقیقه بعد دوباره امتحان کنید.');
            header('Location: ?p=login');
            exit;
        }

        $row = Database::one('SELECT * FROM promoters WHERE mobile = ? AND is_active = 1', [$mobile]);
        if (!$row || empty($row['pin']) || !hash_equals((string) $row['pin'], $pin)) {
            $_SESSION['mobo_attempts']++;
            $_SESSION['mobo_last_attempt'] = time();
            flash_set('error', 'شماره موبایل یا رمز (PIN) اشتباه است.');
            header('Location: ?p=login');
            exit;
        }

        session_regenerate_id(true);
        $_SESSION['mobo_pid'] = (int) $row['id'];
        $_SESSION['mobo_attempts'] = 0;
        header('Location: ?p=products');
        exit;
    }

    if (!$promoter) {
        header('Location: ?p=login');
        exit;
    }

    /* افزودن/کم‌کردن تعداد در سبد */
    if ($p === 'cart') {
        $pid = (int) ($_POST['product_id'] ?? 0);
        $product = Database::one('SELECT id FROM products WHERE id = ? AND is_active = 1', [$pid]);
        if ($product) {
            $cart = is_array($_SESSION['mobo_cart'] ?? null) ? $_SESSION['mobo_cart'] : [];
            if (isset($_POST['delta'])) {
                $qty = (int) ($cart[$pid] ?? 0) + (int) $_POST['delta'];
            } else {
                $qty = mobo_num($_POST['qty'] ?? '0');
            }
            $qty = max(0, $qty);
            if ($qty === 0) {
                unset($cart[$pid]);
            } else {
                $cart[$pid] = min(9999, $qty);
            }
            $_SESSION['mobo_cart'] = $cart;
        }
        header('Location: ?p=' . (($_POST['back'] ?? 'products') === 'cart' ? 'cart' : 'products'));
        exit;
    }

    /* ثبت نهایی سفارش */
    if ($p === 'submit') {
        $cart = is_array($_SESSION['mobo_cart'] ?? null) ? $_SESSION['mobo_cart'] : [];
        $ids = array_map('intval', array_keys($cart));
        if (!$ids) {
            flash_set('error', 'سبد سفارش خالی است.');
            header('Location: ?p=products');
            exit;
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $products = Database::all("SELECT id, name, sale_price FROM products WHERE is_active = 1 AND id IN ($ph)", $ids);
        $items = [];
        $subtotal = 0.0;
        foreach ($products as $pr) {
            $qty = (int) ($cart[(int) $pr['id']] ?? 0);
            if ($qty <= 0) continue;
            $line = round((float) $pr['sale_price'] * $qty, 2);
            $subtotal += $line;
            $items[] = [
                'product_id' => (int) $pr['id'],
                'description' => (string) $pr['name'],
                'qty' => $qty,
                'unit_price' => (float) $pr['sale_price'],
                'total' => $line,
            ];
        }
        if (!$items) {
            flash_set('error', 'هیچ کالای فعالی در سبد نیست.');
            header('Location: ?p=products');
            exit;
        }

        $subtotal = round($subtotal, 2);
        $no = InventoryService::nextNo('invoice_prefix', 'invoice_counter');

        // سفارش موبوآردر: بدون حرکت انبار و بدون سند — پس از تأیید مدیر ثبت نهایی می‌شود
        $invoiceId = Database::insert('invoices', [
            'invoice_no' => $no,
            'invoice_type' => 'sale',
            'customer_id' => null,
            'supplier_name' => null,
            'employee_id' => null,
            'promoter_id' => (int) $promoter['id'],
            'invoice_date' => today(),
            'due_date' => null,
            'subtotal' => $subtotal,
            'discount' => 0.0,
            'tax_rate' => 0.0,
            'tax_amount' => 0.0,
            'shipping' => 0.0,
            'total' => $subtotal,
            'paid_amount' => 0.0,
            'status' => 'pending',
            'payment_method' => null,
            'description' => 'سفارش موبوآردر — ' . (string) $promoter['full_name'],
            'source' => 'moboorder',
            'created_by' => null,
        ]);

        foreach ($items as $it) {
            Database::insert('invoice_items', [
                'invoice_id' => $invoiceId,
                'product_id' => $it['product_id'],
                'description' => $it['description'],
                'qty' => $it['qty'],
                'unit_price' => $it['unit_price'],
                'discount' => 0.0,
                'total' => $it['total'],
            ]);
        }

        unset($_SESSION['mobo_cart']);
        $_SESSION['mobo_last_order'] = ['no' => $no, 'total' => $subtotal, 'count' => count($items)];
        header('Location: ?p=success');
        exit;
    }

    // سایر POSTها → محصولات
    header('Location: ?p=products');
    exit;
}

/* ============================ نماها ============================ */

/* ---------- ورود ---------- */
if ($p === 'login') {
    if ($promoter) {
        header('Location: ?p=products');
        exit;
    }
    mobo_head('ورود مبلغ');
    echo '<div class="login-wrap">'
        . '<div class="login-card">'
        . '<div class="login-logo">د</div>'
        . '<h1>داریا موبوآردر</h1>'
        . '<p class="muted">سفارش‌گیری مبلغان — با موبایل و PIN وارد شوید</p>';
    mobo_flashes();
    echo '<form method="post" action="?p=login">'
        . csrf_field()
        . '<label class="form-label">شماره موبایل</label>'
        . '<input class="form-input" name="mobile" dir="ltr" inputmode="tel" required placeholder="09xxxxxxxxx" autofocus>'
        . '<label class="form-label">رمز (PIN)</label>'
        . '<input class="form-input" name="pin" type="password" dir="ltr" inputmode="numeric" maxlength="6" required placeholder="••••">'
        . '<button class="btn-block" type="submit">ورود</button>'
        . '</form>'
        . '<p class="hint">شماره موبایل و PIN توسط مدیر سامانه در بخش «مبلغان» ثبت می‌شود.</p>'
        . '</div></div>';
    mobo_foot();
    exit;
}

/* ---------- موفقیت سفارش ---------- */
if ($p === 'success') {
    $order = is_array($_SESSION['mobo_last_order'] ?? null) ? $_SESSION['mobo_last_order'] : null;
    unset($_SESSION['mobo_last_order']);
    mobo_head('ثبت سفارش');
    mobo_topbar($promoter, 0);
    mobo_flashes();
    echo '<div class="success-wrap">'
        . '<div class="success-icon">✔</div>'
        . '<h1>سفارش شما ثبت شد</h1>';
    if ($order) {
        echo '<p>شماره سفارش: <b dir="ltr">' . e($order['no']) . '</b></p>'
            . '<p class="muted">' . fa_num($order['count']) . ' قلم — مبلغ: ' . money($order['total']) . ' ' . e(setting('currency', 'تومان')) . '</p>';
    }
    echo '<p class="muted">پس از تأیید مدیر، موجودی و حسابداری سفارش ثبت نهایی می‌شود.</p>'
        . '<a class="btn-block" href="?p=products">بازگشت به محصولات</a>'
        . '</div>';
    mobo_foot();
    exit;
}

/* ---------- لیست محصولات ---------- */
if ($p === 'products') {
    $cart = is_array($_SESSION['mobo_cart'] ?? null) ? $_SESSION['mobo_cart'] : [];
    $cartCount = (int) array_sum(array_map('intval', $cart));
    $products = Database::all('SELECT id, name, unit, sale_price, stock_qty FROM products WHERE is_active = 1 ORDER BY name ASC');

    $cartTotal = 0.0;
    if ($cart) {
        $ids = array_map('intval', array_keys($cart));
        $ph = implode(',', array_fill(0, count($ids), '?'));
        foreach (Database::all("SELECT id, sale_price FROM products WHERE id IN ($ph)", $ids) as $row) {
            $cartTotal += (float) $row['sale_price'] * (int) ($cart[(int) $row['id']] ?? 0);
        }
    }

    mobo_head('محصولات');
    mobo_topbar($promoter, $cartCount);
    mobo_flashes();
    echo '<div class="p-grid">';
    if (!$products) {
        echo '<div class="empty-state">کالایی برای سفارش موجود نیست.</div>';
    }
    foreach ($products as $pr) {
        $pid = (int) $pr['id'];
        $qty = (int) ($cart[$pid] ?? 0);
        $inStock = (float) $pr['stock_qty'] > 0;
        echo '<div class="p-card">'
            . '<div class="p-thumb">' . e(mb_substr((string) $pr['name'], 0, 1)) . '</div>'
            . '<div class="p-name">' . e($pr['name']) . '</div>'
            . '<div class="p-price">' . money($pr['sale_price']) . ' <small>تومان</small></div>'
            . '<div>' . ($inStock
                ? '<span class="badge badge-green">موجودی: ' . e(fa_num(rtrim(rtrim(number_format((float) $pr['stock_qty'], 3, '.', ''), '0'), '.'))) . '</span>'
                : '<span class="badge badge-red">ناموجود</span>') . '</div>'
            . '<form class="stepper" method="post" action="?p=cart">'
            . csrf_field()
            . '<input type="hidden" name="product_id" value="' . $pid . '">'
            . '<input type="hidden" name="back" value="products">'
            . '<button class="step-btn" name="delta" value="-1" aria-label="کاهش">−</button>'
            . '<span class="step-qty">' . fa_num($qty) . '</span>'
            . '<button class="step-btn" name="delta" value="1" aria-label="افزایش">+</button>'
            . '</form>'
            . '</div>';
    }
    echo '</div>';
    if ($cartCount > 0) {
        echo '<div class="cart-bar">'
            . '<div class="cart-total"><small>جمع سبد</small><b>' . money($cartTotal) . '</b></div>'
            . '<a class="btn-cart" href="?p=cart">مشاهده سبد (' . fa_num($cartCount) . ')</a>'
            . '</div>';
    }
    mobo_foot();
    exit;
}

/* ---------- سبد سفارش ---------- */
if ($p === 'cart') {
    $cart = is_array($_SESSION['mobo_cart'] ?? null) ? $_SESSION['mobo_cart'] : [];
    $lines = [];
    $grand = 0.0;
    if ($cart) {
        $ids = array_map('intval', array_keys($cart));
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = Database::all("SELECT id, name, unit, sale_price FROM products WHERE is_active = 1 AND id IN ($ph)", $ids);
        foreach ($rows as $pr) {
            $qty = (int) ($cart[(int) $pr['id']] ?? 0);
            if ($qty <= 0) continue;
            $line = (float) $pr['sale_price'] * $qty;
            $grand += $line;
            $lines[] = ['id' => (int) $pr['id'], 'name' => (string) $pr['name'], 'unit' => (string) $pr['unit'], 'price' => (float) $pr['sale_price'], 'qty' => $qty, 'total' => $line];
        }
    }

    mobo_head('سبد سفارش');
    mobo_topbar($promoter, array_sum(array_map('intval', $cart)));
    mobo_flashes();
    echo '<div class="cart-page">';
    if (!$lines) {
        echo '<div class="empty-state">سبد سفارش خالی است.<br><a href="?p=products">مشاهده محصولات</a></div>';
    } else {
        foreach ($lines as $ln) {
            echo '<div class="c-line">'
                . '<div class="c-info"><div class="c-name">' . e($ln['name']) . '</div>'
                . '<div class="c-price">' . money($ln['price']) . ' <small>تومان / ' . e($ln['unit']) . '</small></div></div>'
                . '<form class="stepper" method="post" action="?p=cart">'
                . csrf_field()
                . '<input type="hidden" name="product_id" value="' . $ln['id'] . '">'
                . '<input type="hidden" name="back" value="cart">'
                . '<button class="step-btn" name="delta" value="-1" aria-label="کاهش">−</button>'
                . '<input class="step-input" name="qty" dir="ltr" inputmode="numeric" value="' . fa_num($ln['qty']) . '">'
                . '<button class="step-btn" name="delta" value="1" aria-label="افزایش">+</button>'
                . '<button class="step-del" name="qty" value="0" aria-label="حذف">✕</button>'
                . '</form>'
                . '<div class="c-total">' . money($ln['total']) . '</div>'
                . '</div>';
        }
        echo '<div class="c-sum">مبلغ کل سبد: <b>' . money($grand) . '</b> ' . e(setting('currency', 'تومان')) . '</div>';
        echo '<form method="post" action="?p=submit">' . csrf_field()
            . '<div class="cart-bar">'
            . '<div class="cart-total"><small>مبلغ کل</small><b>' . money($grand) . '</b></div>'
            . '<button class="btn-cart" type="submit">ثبت سفارش</button>'
            . '</div></form>';
    }
    echo '</div>';
    mobo_foot();
    exit;
}

/* ---------- پیش‌فرض ---------- */
header('Location: ?p=login');
exit;

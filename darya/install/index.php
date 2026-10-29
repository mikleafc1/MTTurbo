<?php
/**
 * نصب‌کننده داریا — مراحل: بررسی پیش‌نیاز → اتصال دیتابیس → ساخت جداول → حساب مدیر → پایان
 */
declare(strict_types=1);
session_start();
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

define('BASE_PATH', dirname(__DIR__));
$lock = BASE_PATH . '/config/installed.lock';

if (file_exists($lock)) {
    die('<!doctype html><html dir="rtl"><meta charset="utf-8"><body style="font-family:Tahoma;text-align:center;padding:80px">داریا قبلاً نصب شده است. <a href="../">ورود به سامانه</a></body></html>');
}

function req_check(): array
{
    return [
        ['PHP 8.1+', version_compare(PHP_VERSION, '8.1.0', '>='), PHP_VERSION],
        ['PDO MySQL', extension_loaded('pdo_mysql'), ''],
        ['mbstring', extension_loaded('mbstring'), ''],
        ['OpenSSL', extension_loaded('openssl'), ''],
        ['cURL (برای افزونه)', extension_loaded('curl'), ''],
        ['GD (اختیاری)', extension_loaded('gd'), ''],
        ['fileinfo (اختیاری)', extension_loaded('fileinfo'), ''],
        ['config قابل نوشتن', is_writable(BASE_PATH . '/config'), '0755'],
        ['uploads قابل نوشتن', is_writable(BASE_PATH . '/uploads') || @mkdir(BASE_PATH . '/uploads', 0755, true), '0755'],
    ];
}

$errors = [];
$done = false;
$apiKey = '';
$step = 'start';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = [
        'host' => trim($_POST['db_host'] ?? 'localhost'),
        'name' => trim($_POST['db_name'] ?? ''),
        'user' => trim($_POST['db_user'] ?? ''),
        'pass' => (string) ($_POST['db_pass'] ?? ''),
        'charset' => 'utf8mb4',
    ];
    $admin = [
        'username' => trim($_POST['admin_user'] ?? ''),
        'password' => (string) ($_POST['admin_pass'] ?? ''),
        'full_name' => trim($_POST['admin_name'] ?? 'مدیر سیستم'),
    ];
    $company = trim($_POST['company_name'] ?? 'شرکت من');

    try {
        if ($admin['username'] === '' || strlen($admin['password']) < 6) {
            throw new RuntimeException('نام کاربری مدیر و رمز عبور (حداقل ۶ کاراکتر) الزامی است.');
        }
        $pdo = new PDO("mysql:host={$db['host']};charset=utf8mb4", $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$db['name']}`");

        // اجرای اسکیما
        $sql = file_get_contents(BASE_PATH . '/database/schema.sql');
        foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $stmt) {
            $stmt = trim(preg_replace('#^--.*$#m', '', $stmt));
            if ($stmt !== '') $pdo->exec($stmt);
        }

        // مدیر کل
        $st = $pdo->prepare('SELECT id FROM roles WHERE slug = ?');
        $st->execute(['admin']);
        $adminRole = $st->fetchColumn();
        $pdo->prepare('INSERT INTO users (username, password_hash, full_name, role_id, is_active) VALUES (?,?,?,?,1)')
            ->execute([$admin['username'], password_hash($admin['password'], PASSWORD_DEFAULT), $admin['full_name'], $adminRole ?: 1]);

        // کلید API (یک‌بار نمایش داده می‌شود)
        $apiKey = 'dk_' . bin2hex(random_bytes(24));
        $pdo->prepare('INSERT INTO api_keys (name, key_prefix, key_hash, scopes) VALUES (?,?,?,?)')
            ->execute(['کلید اصلی همگام‌سازی', substr($apiKey, 0, 10), hash('sha256', $apiKey), '*']);

        $pdo->prepare('UPDATE settings SET `value` = ? WHERE `key` = ?')->execute([$company, 'company_name']);

        // نوشتن config
        $tpl = <<<PHP
<?php
return [
    'debug'    => false,
    'timezone' => 'Asia/Tehran',
    'db' => [
        'host'    => '%s',
        'name'    => '%s',
        'user'    => '%s',
        'pass'    => '%s',
        'charset' => 'utf8mb4',
    ],
];
PHP;
        $conf = sprintf($tpl, addslashes($db['host']), addslashes($db['name']), addslashes($db['user']), addslashes($db['pass']));
        file_put_contents(BASE_PATH . '/config/config.php', $conf);
        @chmod(BASE_PATH . '/config/config.php', 0644);
        @mkdir(BASE_PATH . '/uploads', 0755, true);
        file_put_contents($lock, date('Y-m-d H:i:s'));

        $step = 'done';
        $done = true;
    } catch (Throwable $e) {
        $errors[] = 'خطا: ' . $e->getMessage();
    }
}
$checks = req_check();
$allOk = !in_array(false, array_column($checks, 1), true);
?>
<!doctype html>
<html dir="rtl" lang="fa">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>نصب داریا</title>
<style>
body{font-family:"Vazirmatn",Tahoma,sans-serif;background:#f4f5f7;color:#1f2937;line-height:1.8;margin:0}
.wrap{max-width:720px;margin:40px auto;padding:0 16px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:26px;margin-bottom:18px}
h1{font-size:22px}h2{font-size:16px;margin-bottom:10px}
table{width:100%;border-collapse:collapse;font-size:13px}
td{padding:7px 4px;border-bottom:1px solid #f1f2f4}
.ok{color:#0f766e;font-weight:700}.bad{color:#b91c1c;font-weight:700}
label{display:block;font-size:13px;color:#6b7280;margin:12px 0 4px}
input{width:100%;padding:10px 12px;border:1px solid #e5e7eb;border-radius:9px;font-family:inherit;font-size:14px;box-sizing:border-box}
button{background:#0f766e;color:#fff;border:none;padding:12px 26px;border-radius:10px;font-family:inherit;font-size:15px;cursor:pointer;margin-top:18px}
.alert{background:#fef2f2;color:#b91c1c;padding:10px 14px;border-radius:10px;margin-bottom:12px;font-size:13px}
.apikey{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:14px;border-radius:10px;direction:ltr;text-align:center;font-family:monospace;font-size:15px;word-break:break-all;user-select:all}
.muted{color:#6b7280;font-size:12.5px}
</style>
</head>
<body>
<div class="wrap">
    <div class="card" style="text-align:center">
        <div style="width:52px;height:52px;background:#0f766e;color:#fff;border-radius:14px;display:inline-flex;align-items:center;justify-content:center;font-size:28px;font-weight:800">د</div>
        <h1>نصب سامانه داریا</h1>
        <p class="muted">حسابداری و مدیریت کسب‌وکار — نسخه ۱.۰.۰</p>
    </div>

    <?php foreach ($errors as $er): ?><div class="alert"><?= htmlspecialchars($er) ?></div><?php endforeach; ?>

    <?php if ($done): ?>
        <div class="card">
            <h2>✅ نصب با موفقیت انجام شد</h2>
            <p>کلید API همگام‌سازی شما (فقط همین یک بار نمایش داده می‌شود — آن را جایی ذخیره کنید):</p>
            <div class="apikey"><?= htmlspecialchars($apiKey) ?></div>
            <p class="muted" style="margin-top:12px">این کلید را در <b>افزونه Darya Bridge</b> (وردپرس) و <b>نرم‌افزار ویندوزی Darya Sync</b> وارد کنید.</p>
            <p style="text-align:center"><a href="../" style="color:#0f766e">ورود به سامانه ←</a></p>
        </div>
    <?php else: ?>
        <div class="card">
            <h2>۱) بررسی پیش‌نیازها</h2>
            <table>
                <?php foreach ($checks as $c): ?>
                    <tr><td><?= htmlspecialchars($c[0]) ?></td><td class="<?= $c[1] ? 'ok' : 'bad' ?>"><?= $c[1] ? 'موجود' : 'ناموجود' ?></td><td class="muted"><?= htmlspecialchars((string) $c[2]) ?></td></tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class="card">
            <h2>۲) اطلاعات دیتابیس و حساب مدیر</h2>
            <?php if (!$allOk): ?><div class="alert">برخی پیش‌نیازها فراهم نیستند؛ نصب ممکن است ناموفق باشد.</div><?php endif; ?>
            <form method="post">
                <label>هاست دیتابیس</label>
                <input name="db_host" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>">
                <label>نام دیتابیس (در صورت نبود ساخته می‌شود)</label>
                <input name="db_name" required value="<?= htmlspecialchars($_POST['db_name'] ?? 'darya') ?>">
                <label>کاربر دیتابیس</label>
                <input name="db_user" required value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>">
                <label>رمز دیتابیس</label>
                <input name="db_pass" type="password" value="">
                <hr style="border:none;border-top:1px solid #e5e7eb;margin:16px 0">
                <label>نام شرکت / فروشگاه</label>
                <input name="company_name" value="<?= htmlspecialchars($_POST['company_name'] ?? 'شرکت من') ?>">
                <label>نام کاربری مدیر کل</label>
                <input name="admin_user" required value="<?= htmlspecialchars($_POST['admin_user'] ?? 'admin') ?>">
                <label>نام و نام‌خانوادگی مدیر</label>
                <input name="admin_name" value="<?= htmlspecialchars($_POST['admin_name'] ?? 'مدیر سیستم') ?>">
                <label>رمز عبور مدیر (حداقل ۶ کاراکتر)</label>
                <input name="admin_pass" type="password" required minlength="6">
                <button type="submit">نصب کن 🚀</button>
            </form>
        </div>
        <p class="muted">پس از نصب، پوشه <code dir="ltr">install/</code> را از هاست حذف کنید.</p>
    <?php endif; ?>
</div>
</body>
</html>

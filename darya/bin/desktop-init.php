<?php
/**
 * داریا دسکتاپ — آماده‌سازی اولیه (CLI)
 * ساخت دیتابیس SQLite + جداول + داده‌های اولیه + حساب مدیر (admin / admin123)
 * + نوشتن config.php — توسط Darya.exe در اولین اجرا فراخوانی می‌شود.
 *
 * مصرف:  php desktop-init.php [مسیر پوشه برنامه]
 */
declare(strict_types=1);
error_reporting(E_ALL & ~E_DEPRECATED);
mb_internal_encoding('UTF-8');

$app = $argv[1] ?? dirname(__DIR__);
define('BASE_PATH', rtrim($app, '/\\'));
$lock    = BASE_PATH . '/config/installed.lock';
$dataDir = BASE_PATH . '/data';
$dbPath  = $dataDir . '/darya.sqlite';

function fail(string $m): void { fwrite(STDERR, 'ERROR: ' . $m . "\n"); exit(1); }

if (!is_dir(BASE_PATH . '/config')) fail('پوشه config یافت نشد: ' . BASE_PATH);
if (!is_writable(BASE_PATH . '/config')) fail('پوشه config قابل نوشتن نیست');
if (!is_dir($dataDir) && !@mkdir($dataDir, 0755, true)) fail('ساخت پوشه data ناموفق');
if (!extension_loaded('pdo_sqlite')) fail('افزونه pdo_sqlite در PHP موجود نیست');

$created = false;
$apiKey  = '';

if (!file_exists($dbPath)) {
    $created = true;
    $pdo = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');

    // اجرای اسکیما
    $sql = @file_get_contents(BASE_PATH . '/database/schema.sqlite.sql');
    if ($sql === false || $sql === '') fail('فایل database/schema.sqlite.sql یافت نشد');
    foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $stmt) {
        $stmt = trim(preg_replace('#^--.*$#m', '', $stmt));
        if ($stmt !== '') $pdo->exec($stmt);
    }

    // حساب مدیر کل
    $st = $pdo->prepare('SELECT id FROM roles WHERE slug = ?');
    $st->execute(['admin']);
    $adminRole = $st->fetchColumn();
    $pdo->prepare('INSERT INTO users (username, password_hash, full_name, role_id, is_active) VALUES (?,?,?,?,1)')
        ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'مدیر سیستم', $adminRole ?: 1]);

    // کلید API همگام‌سازی
    $apiKey = 'dk_' . bin2hex(random_bytes(24));
    $pdo->prepare('INSERT INTO api_keys (name, key_prefix, key_hash, scopes) VALUES (?,?,?,?)')
        ->execute(['کلید اصلی همگام‌سازی', substr($apiKey, 0, 10), hash('sha256', $apiKey), '*']);

    // تنظیمات اولیه
    $pdo->prepare('UPDATE settings SET "value" = ? WHERE "key" = ?')->execute(['شرکت من', 'company_name']);
    if ((int) $pdo->query("SELECT COUNT(*) FROM settings WHERE \"key\" = 'company_name'")->fetchColumn() === 0) {
        $pdo->prepare('INSERT INTO settings ("key", "value") VALUES (?,?)')->execute(['company_name', 'شرکت من']);
    }

    file_put_contents($dataDir . '/api-key.txt', $apiKey . "\n");
    $pdo = null;
}

// نوشتن config.php (همیشه — تا مسیر دیتابیس تضمین‌شده باشد)
if (!file_exists($lock)) {
    $conf = "<?php\nreturn [\n    'debug'    => false,\n    'timezone' => 'Asia/Tehran',\n    'db' => [\n        'driver' => 'sqlite',\n        'path'   => __DIR__ . '/../data/darya.sqlite',\n    ],\n];\n";
    file_put_contents(BASE_PATH . '/config/config.php', $conf);
    @mkdir(BASE_PATH . '/uploads', 0755, true);
    file_put_contents($lock, date('Y-m-d H:i:s'));
}

echo json_encode([
    'ok'      => true,
    'created' => $created,
    'db'      => $dbPath,
    'api_key' => $apiKey,
], JSON_UNESCAPED_UNICODE), "\n";

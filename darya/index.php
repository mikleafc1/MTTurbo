<?php
/**
 * Darya — سامانه مدیریت و حسابداری فارسی
 * Front controller
 */
declare(strict_types=1);

define('BASE_PATH', __DIR__);

if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    http_response_code(500);
    die('Darya requires PHP 8.1+ / نسخه پی‌اچ‌پی سرور شما قدیمی است (حداقل 8.1)');
}

if (!file_exists(BASE_PATH . '/config/config.php')) {
    header('Location: install/');
    exit;
}

$config = require BASE_PATH . '/config/config.php';
$GLOBALS['darya_config'] = $config;

spl_autoload_register(function (string $class): void {
    $rel = str_replace('\\', '/', $class) . '.php';
    if (str_starts_with($rel, 'App/')) {
        $rel = 'app' . substr($rel, 3); // پوشه واقعی با حروف کوچک (سازگاری لینوکس/ویندوز)
    }
    $file = BASE_PATH . '/' . $rel;
    if (is_file($file)) {
        require $file;
    }
});

require_once BASE_PATH . '/app/Core/helpers.php';

date_default_timezone_set($config['timezone'] ?? 'Asia/Tehran');
mb_internal_encoding('UTF-8');

session_name('DARYASESSID');
session_start();

error_reporting(E_ALL);
ini_set('display_errors', (!empty($config['debug'])) ? '1' : '0');
ini_set('log_errors', '1');

set_exception_handler(function (Throwable $e) {
    error_log('[DARYA] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    if (!empty($GLOBALS['darya_config']['debug'])) {
        echo '<pre dir="ltr">' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
        return;
    }
    echo '<!doctype html><html dir="rtl" lang="fa"><head><meta charset="utf-8"><title>خطا</title></head>'
        . '<body style="font-family:Tahoma;text-align:center;padding:60px 20px">'
        . '<h2 style="color:#b91c1c">خطای سیستمی</h2><p>مشکلی رخ داده است. لطفاً دوباره تلاش کنید.</p></body></html>';
});

\App\Core\App::run();

// Flash cleanup at end of request
flash_consume();

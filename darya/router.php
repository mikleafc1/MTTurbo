<?php
/**
 * داریا — روتر وب‌سرور داخلی PHP (نسخه دسکتاپ ویندوزی)
 * مصرف:  php -S 127.0.0.1:8765 -t . router.php
 * روی آپاچی/هاست اشتراکی این فایل استفاده نمی‌شود (جای آن .htaccess است).
 */
declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $path;

// فایل‌های واقعی (css/js/تصویر/...) مستقیم سرو شوند
if ($path !== '/' && is_file($file)) {
    return false;
}

// زیربرنامه‌های مستقل (mobo/...) که index.php خودشان را دارند
if ($path !== '/' && is_dir($file) && is_file($file . '/index.php')) {
    chdir($file);
    $_SERVER['SCRIPT_NAME'] = rtrim($path, '/') . '/index.php';
    require $file . '/index.php';
    exit;
}

// تمام مسیرهای تمیز به front controller با پارامتر route
if (!isset($_GET['route'])) {
    $_GET['route'] = ltrim($path, '/');
}

require __DIR__ . '/index.php';

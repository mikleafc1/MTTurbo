<?php
/**
 * Darya configuration (sample)
 * نصب‌کننده به‌صورت خودکار همین فایل را با مقادیر شما در config.php می‌سازد.
 */
return [
    'debug'    => false,
    'timezone' => 'Asia/Tehran',
    // driver: 'mysql' (هاست وب) یا 'sqlite' (نسخه دسکتاپ ویندوزی)
    'db' => [
        'driver'  => 'mysql',
        'host'    => 'localhost',
        'name'    => 'darya_db',
        'user'    => 'db_user',
        'pass'    => 'db_pass',
        'charset' => 'utf8mb4',
        // برای driver = 'sqlite':
        // 'path' => BASE_PATH . '/data/darya.sqlite',
    ],
];

<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));
if (!file_exists(BASE_PATH . '/config/config.php')) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    die(json_encode(['ok' => false, 'error' => ['code' => 'not_installed', 'message' => 'Darya is not installed yet']]));
}
$config = require BASE_PATH . '/config/config.php';
$GLOBALS['darya_config'] = $config;

spl_autoload_register(function (string $class): void {
    $rel = str_replace('\\', '/', $class) . '.php';
    if (str_starts_with($rel, 'App/')) {
        $rel = 'app' . substr($rel, 3); // پوشه واقعی با حروف کوچک (سازگاری لینوکس/ویندوز)
    }
    $file = BASE_PATH . '/' . $rel;
    if (is_file($file)) require $file;
});
require_once BASE_PATH . '/app/Core/helpers.php';

date_default_timezone_set($config['timezone'] ?? 'Asia/Tehran');

\App\Api\Kernel::handle($_GET['path'] ?? '');

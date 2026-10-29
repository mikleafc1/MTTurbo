<?php
declare(strict_types=1);
namespace App\Core;

class App
{
    /**
     * نگاشت مسیر جمع به نام کلاس کنترلر (بعضی کنترلرها مفرد هستند)
     * اول کلاس هم‌نام مسیر جستجو می‌شود، سپس نگاشت.
     */
    private const MODULE_ALIASES = [
        'invoices'   => 'invoice',
        'products'   => 'product',
        'categories' => 'category',
        'warehouses' => 'warehouse',
        'promoters'  => 'promoter',
        'purchases'  => 'purchase',
    ];

    public static function run(): void
    {
        $route = trim((string) ($_GET['route'] ?? ''), '/');
        $route = preg_replace('#[^a-zA-Z0-9_/\-]#', '', $route) ?: 'dashboard/index';

        $parts = array_values(array_filter(explode('/', $route), fn($p) => $p !== ''));
        $module = $parts[0] ?? 'dashboard';
        $action = $parts[1] ?? 'index';
        $args   = array_slice($parts, 2);

        $moduleLc = strtolower($module);
        $try = [$moduleLc];
        if (isset(self::MODULE_ALIASES[$moduleLc])) {
            $try[] = self::MODULE_ALIASES[$moduleLc];
        }
        $class = null;
        foreach ($try as $t) {
            $c = 'App\\Controllers\\' . ucfirst($t) . 'Controller';
            if (class_exists($c)) { $class = $c; break; }
        }
        $method = lcfirst(str_replace('-', '', ucwords(strtolower($action), '-'))) . 'Action';

        if ($class === null) {
            self::dispatch404();
        }
        $controller = new $class();

        // Auth gate
        if (!$controller->public) {
            if (!Auth::check()) {
                $_SESSION['intended'] = $route;
                flash_set('error', 'برای دسترسی ابتدا وارد شوید.');
                header('Location: ' . url('login'));
                exit;
            }
            // CSRF for every authenticated POST (HTML forms)
            if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
                Csrf::verify();
            }
        }

        if (!method_exists($controller, $method)) {
            self::dispatch404();
        }
        call_user_func([$controller, $method], $args);
    }

    private static function dispatch404(): void
    {
        http_response_code(404);
        if (is_ajax()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => ['code' => 'not_found', 'message' => 'یافت نشد']], JSON_UNESCAPED_UNICODE);
        } else {
            require BASE_PATH . '/app/Views/errors/404.php';
        }
        exit;
    }
}

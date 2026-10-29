<?php
declare(strict_types=1);
namespace App\Core;

class App
{
    public static function run(): void
    {
        $route = trim((string) ($_GET['route'] ?? ''), '/');
        $route = preg_replace('#[^a-zA-Z0-9_/\-]#', '', $route) ?: 'dashboard/index';

        $parts = array_values(array_filter(explode('/', $route), fn($p) => $p !== ''));
        $module = $parts[0] ?? 'dashboard';
        $action = $parts[1] ?? 'index';
        $args   = array_slice($parts, 2);

        $class = 'App\\Controllers\\' . ucfirst(strtolower($module)) . 'Controller';
        $method = lcfirst(str_replace('-', '', ucwords(strtolower($action), '-'))) . 'Action';

        if (!class_exists($class)) {
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

<?php
declare(strict_types=1);
namespace App\Core;

abstract class Controller
{
    /** Public controllers skip login (e.g. LoginController) */
    public bool $public = false;

    protected function view(string $path, array $data = [], string $layout = 'app'): void
    {
        extract($data, EXTR_SKIP);
        $viewFile = BASE_PATH . '/app/Views/' . $path . '.php';
        if (!is_file($viewFile)) {
            throw new \RuntimeException("View not found: $path");
        }
        ob_start();
        require $viewFile;
        $content = ob_get_clean();
        $layoutFile = BASE_PATH . '/app/Views/layouts/' . $layout . '.php';
        require $layoutFile;
    }

    protected function render(string $path, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require BASE_PATH . '/app/Views/' . $path . '.php';
    }

    protected function json($data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function redirect(string $route): never
    {
        header('Location: ' . url($route));
        exit;
    }

    protected function input(string $key, $default = null)
    {
        $v = $_POST[$key] ?? $_GET[$key] ?? $default;
        return is_string($v) ? trim($v) : $v;
    }

    protected function postAll(): array
    {
        return $_POST;
    }

    protected function requirePerm(string $perm): void
    {
        if (!Auth::can($perm)) {
            flash_set('error', 'شما به این بخش دسترسی ندارید.');
            $this->redirect('dashboard');
        }
    }

    protected function notFound(): never
    {
        http_response_code(404);
        $this->view('errors/404');
        exit;
    }

    protected function paginate(string $table, string $where = '1=1', array $params = [], int $perPage = 20, string $select = '*', string $orderBy = 'id DESC'): array
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $total = (int) Database::val("SELECT COUNT(*) FROM `$table` WHERE $where", $params);
        $rows = Database::all(
            "SELECT $select FROM `$table` WHERE $where ORDER BY $orderBy LIMIT " . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
            $params
        );
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $perPage))];
    }
}

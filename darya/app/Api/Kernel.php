<?php
declare(strict_types=1);
namespace App\Api;

use App\Core\Database;

class Kernel
{
    public static function handle(string $path): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type, X-Api-Key');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
            http_response_code(204);
            exit;
        }

        $path = trim(str_replace('api/index.php', '', $path), '/');
        if (!$path) {
            $uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '';
            $path = trim(str_replace(['/api/index.php', '/api'], '', $uri), '/');
        }

        try {
            $key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
            if (!$key) {
                self::error(401, 'unauthorized', 'کلید API ارسال نشده است (هدر X-Api-Key)');
            }
            $keyRow = Database::one('SELECT * FROM api_keys WHERE key_hash = ? AND is_active = 1', [hash('sha256', (string) $key)]);
            if (!$keyRow) {
                self::error(401, 'unauthorized', 'کلید API نامعتبر است');
            }
            Database::q('UPDATE api_keys SET last_used_at = ? WHERE id = ?', [now(), $keyRow['id']]);

            $body = [];
            if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH'], true)) {
                $raw = file_get_contents('php://input') ?: '';
                $body = json_decode($raw, true);
                if (!is_array($body)) {
                    $body = $_POST ?: [];
                }
            }

            $result = \App\Services\SyncService::route($path, $body, $keyRow);
            self::ok($result);
        } catch (\App\Api\ApiError $e) {
            self::error($e->status, $e->errorCode, $e->getMessage());
        } catch (\Throwable $e) {
            error_log('[DARYA API] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            self::error(500, 'server_error', 'خطای داخلی سرور: ' . $e->getMessage());
        }
    }

    public static function ok($data): never
    {
        echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function error(int $status, string $code, string $message): never
    {
        http_response_code($status);
        echo json_encode(['ok' => false, 'error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

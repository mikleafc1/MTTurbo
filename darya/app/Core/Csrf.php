<?php
declare(strict_types=1);
namespace App\Core;

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(20));
        }
        return $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . self::token() . '">';
    }

    public static function verify(): void
    {
        $sent = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!$sent || !hash_equals(self::token(), (string) $sent)) {
            http_response_code(419);
            if (is_ajax()) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => ['code' => 'csrf', 'message' => 'توکن امنیتی نامعتبر است. صفحه را دوباره بارگذاری کنید.']], JSON_UNESCAPED_UNICODE);
            } else {
                die('<!doctype html><html dir="rtl"><meta charset="utf-8"><body style="font-family:Tahoma;text-align:center;padding:60px"><h2>۴۱۹ — نشست منقضی شده</h2><p>لطفاً صفحه قبلی را دوباره بارگذاری کنید.</p></body></html>');
            }
            exit;
        }
    }
}

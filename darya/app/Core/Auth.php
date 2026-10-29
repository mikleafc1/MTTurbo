<?php
declare(strict_types=1);
namespace App\Core;

class Auth
{
    private static ?array $user = null;

    public static function check(): bool
    {
        return !empty($_SESSION['uid']);
    }

    public static function attempt(string $username, string $password): bool
    {
        // Simple brute-force throttle per session
        $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0);
        $_SESSION['last_attempt']   = $_SESSION['last_attempt'] ?? 0;
        if ($_SESSION['login_attempts'] >= 5 && (time() - $_SESSION['last_attempt']) < 300) {
            return false;
        }

        $user = Database::one(
            "SELECT u.*, r.slug AS role_slug, r.name AS role_name, r.permissions
             FROM users u JOIN roles r ON r.id = u.role_id
             WHERE u.username = ? AND u.is_active = 1",
            [$username]
        );
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt'] = time();
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['uid'] = (int) $user['id'];
        $_SESSION['login_attempts'] = 0;
        Database::update('users', ['last_login_at' => now()], 'id = ?', [$user['id']]);
        self::$user = null;
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', time() - 42000, '/');
        }
        session_destroy();
    }

    public static function user(): ?array
    {
        if (self::$user === null && self::check()) {
            self::$user = Database::one(
                "SELECT u.*, r.slug AS role_slug, r.name AS role_name, r.permissions
                 FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?",
                [(int) $_SESSION['uid']]
            );
            if (!self::$user) {
                self::logout();
                return null;
            }
        }
        return self::$user;
    }

    public static function can(string $perm): bool
    {
        $u = self::user();
        if (!$u) return false;
        if ($u['role_slug'] === 'admin') return true;
        $perms = json_decode((string) $u['permissions'], true) ?: [];
        return in_array('*', $perms, true) || in_array($perm, $perms, true);
    }
}

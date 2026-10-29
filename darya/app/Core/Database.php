<?php
declare(strict_types=1);
namespace App\Core;

class Database
{
    private static ?\PDO $pdo = null;

    public static function pdo(): \PDO
    {
        if (self::$pdo === null) {
            $c = config('db');
            $opts = [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ];
            if (($c['driver'] ?? 'mysql') === 'sqlite') {
                // نسخه دسکتاپ / نصب بدون MySQL
                $path = $c['path'] ?? (BASE_PATH . '/data/darya.sqlite');
                $dir = dirname($path);
                if (!is_dir($dir)) @mkdir($dir, 0755, true);
                self::$pdo = new \PDO('sqlite:' . $path, null, null, $opts);
                self::$pdo->exec('PRAGMA journal_mode = WAL');
                self::$pdo->exec('PRAGMA foreign_keys = ON');
                self::$pdo->exec('PRAGMA busy_timeout = 5000');
            } else {
                $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $c['host'], $c['name'], $c['charset'] ?? 'utf8mb4');
                $opts[\PDO::ATTR_EMULATE_PREPARES] = false;
                self::$pdo = new \PDO($dsn, $c['user'], $c['pass'], $opts);
            }
        }
        return self::$pdo;
    }

    public static function q(string $sql, array $params = []): \PDOStatement
    {
        try {
            $st = self::pdo()->prepare($sql);
            $st->execute($params);
        } catch (\Throwable $e) {
            error_log('[DARYA-SQL] ' . $e->getMessage() . ' <<SQL>> ' . $sql . ' <<PARAMS>> ' . json_encode($params, JSON_UNESCAPED_UNICODE));
            throw $e;
        }
        return $st;
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::q($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll();
    }

    public static function val(string $sql, array $params = [])
    {
        $v = self::q($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = "INSERT INTO `$table` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', array_fill(0, count($cols), '?')) . ")";
        self::q($sql, array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = [];
        foreach (array_keys($data) as $c) {
            $set[] = "`$c` = ?";
        }
        $sql = "UPDATE `$table` SET " . implode(', ', $set) . " WHERE $where";
        return self::q($sql, array_merge(array_values($data), $whereParams))->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::q("DELETE FROM `$table` WHERE $where", $params)->rowCount();
    }
}

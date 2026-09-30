<?php
declare(strict_types=1);

function load_env(string $file): void {
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $value = trim($value);
        if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) $value = substr($value, 1, -1);
        $_ENV[trim($key)] = $value;
    }
}
$envFile = getenv('APP_ENV_FILE') ?: ($_ENV['APP_ENV_FILE'] ?? dirname(__DIR__) . '/.env');
load_env((string)$envFile);
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'httponly' => true,
    'secure' => ($_ENV['SESSION_SECURE'] ?? '0') === '1',
    'samesite' => 'Lax',
    'path' => '/',
]);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/** Apply the configured prefix only to known application table names in SQL clauses.
 * This keeps SQL identifiers out of user input and lets the same code run with
 * unprefixed local tables or host-prefixed shared-hosting tables.
 */
function prefix_sql_tables(string $sql): string {
    static $tables = ['users','company_settings','accounting_periods','accounts','vouchers','voucher_lines','audit_logs','ai_settings','ai_intake_jobs'];
    $prefix = (string)($_ENV['DB_TABLE_PREFIX'] ?? '');
    if ($prefix === '') return $sql;
    if (!preg_match('/^[A-Za-z0-9_]{1,40}$/', $prefix)) throw new RuntimeException('DB_TABLE_PREFIX 格式無效。');
    $names = implode('|', array_map(static fn($name) => preg_quote($name, '/'), $tables));
    return preg_replace_callback('/\\b(FROM|JOIN|UPDATE|INTO|TABLE|REFERENCES)(\\s+)(`?)(' . $names . ')(`?)(?=\\b)/i',
        static fn($m) => $m[1] . $m[2] . $m[3] . $prefix . $m[4] . $m[5], $sql);
}

class AccountingPDO extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return parent::prepare(prefix_sql_tables($query), $options);
    }
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        $query = prefix_sql_tables($query);
        return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
    }
    public function exec(string $statement): int|false {
        return parent::exec(prefix_sql_tables($statement));
    }
}

function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $_ENV['DB_HOST'] ?? '127.0.0.1', $_ENV['DB_PORT'] ?? '3306', $_ENV['DB_NAME'] ?? 'accounting_basic');
        $pdo = new AccountingPDO($dsn, $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASS'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false]);
    }
    return $pdo;
}
require_once __DIR__ . '/helpers.php';

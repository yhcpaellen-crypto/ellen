<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';
$dbName = $_ENV['DB_NAME'] ?? 'accounting_basic';
$rootUser = getenv('MYSQL_ROOT_USER') ?: 'root';
$rootPass = getenv('MYSQL_ROOT_PASSWORD') ?: '';
$dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
$pdo = new PDO($dsn, $rootUser, $rootPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$appPassword = bin2hex(random_bytes(32));
$pdo->exec("CREATE USER IF NOT EXISTS 'accounting_app'@'localhost' IDENTIFIED BY '{$appPassword}'");
$pdo->exec("ALTER USER 'accounting_app'@'localhost' IDENTIFIED BY '{$appPassword}'");
$pdo->exec("GRANT SELECT, INSERT, UPDATE, DELETE ON `{$dbName}`.* TO 'accounting_app'@'localhost'");

$content = "APP_NAME=\"會計帳務基本版\"\nAPP_ENV=local\nAPP_DEBUG=0\nAPP_TIMEZONE=Asia/Taipei\nDB_HOST={$host}\nDB_PORT={$port}\nDB_NAME={$dbName}\nDB_USER=accounting_app\nDB_PASS={$appPassword}\nSESSION_SECURE=0\n";
$path = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
if (file_put_contents($path, $content, LOCK_EX) === false) {
    fwrite(STDERR, "無法寫入應用程式設定檔 .env\n");
    exit(1);
}
echo "已建立專用資料庫帳號，並寫入網站根目錄之外的 .env 設定。\n";

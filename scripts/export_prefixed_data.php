<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
$prefix = $argv[1] ?? '';
if (!preg_match('/^[A-Za-z0-9_]{1,40}$/', $prefix)) {
    fwrite(STDERR, "Usage: php scripts/export_prefixed_data.php TABLE_PREFIX\n");
    exit(2);
}
$bootstrap = getenv('ACCOUNTING_BOOTSTRAP') ?: dirname(__DIR__) . '/app/bootstrap.php';
if (!is_file($bootstrap)) { fwrite(STDERR, "Cannot locate application bootstrap\n"); exit(1); }
ini_set('session.save_path', sys_get_temp_dir());
require $bootstrap;

$pdo = db();
$tables = ['users','company_settings','accounting_periods','accounts','ai_settings','vouchers','voucher_lines','audit_logs','ai_intake_jobs'];
echo "SET FOREIGN_KEY_CHECKS=0;\nSTART TRANSACTION;\n";
foreach ($tables as $table) {
    $columns = $pdo->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_COLUMN);
    $rows = $pdo->query('SELECT * FROM `' . $table . '`')->fetchAll(PDO::FETCH_ASSOC);
    if (!$columns || !$rows) continue;
    $quotedColumns = implode(',', array_map(static fn($c) => '`' . str_replace('`','``',$c) . '`', $columns));
    foreach ($rows as $row) {
        $values = [];
        foreach ($columns as $column) $values[] = $row[$column] === null ? 'NULL' : $pdo->quote((string)$row[$column]);
        echo 'REPLACE INTO `' . $prefix . $table . '` (' . $quotedColumns . ') VALUES (' . implode(',', $values) . ");\n";
    }
}
echo "COMMIT;\nSET FOREIGN_KEY_CHECKS=1;\n";

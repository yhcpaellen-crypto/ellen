<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
$prefix = $argv[1] ?? '';
if (!preg_match('/^[A-Za-z0-9_]{1,40}$/', $prefix)) {
    fwrite(STDERR, "Usage: php scripts/build_prefixed_schema.php TABLE_PREFIX\n");
    exit(2);
}
$schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
if ($schema === false) { fwrite(STDERR, "Cannot read database/schema.sql\n"); exit(1); }
$schema = preg_replace('/^\s*CREATE\s+DATABASE\b.*?;\s*/i', '', $schema, 1);
$schema = preg_replace('/^\s*USE\s+`?[A-Za-z0-9_]+`?\s*;\s*/i', '', $schema, 1);
$tables = ['users','company_settings','accounting_periods','accounts','vouchers','voucher_lines','audit_logs','ai_settings','ai_intake_jobs'];
foreach ($tables as $table) {
    $schema = preg_replace('/(?<![A-Za-z0-9_])' . preg_quote($table, '/') . '(?![A-Za-z0-9_])/', $prefix . $table, $schema);
}
$schema = preg_replace_callback('/(CONSTRAINT\s+)(`?)([A-Za-z0-9_]+)(`?)/i',
    static fn($m) => $m[1] . $m[2] . $prefix . $m[3] . $m[4], $schema);
echo $schema;

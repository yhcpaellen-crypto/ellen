<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
$username = $argv[1] ?? 'admin';
$display = $argv[2] ?? '系統管理者';
$password = getenv('ADMIN_PASSWORD');
if ($password === false || $password === '') $password = readline('管理者密碼（至少 12 字元）: ');
if (strlen($password) < 12) { fwrite(STDERR, "密碼至少需要 12 個字元。\n"); exit(1); }
$check = db()->prepare('SELECT id FROM users WHERE username = ?');
$check->execute([$username]);
if ($check->fetch()) { fwrite(STDERR, "此帳號已存在。\n"); exit(1); }
$q = db()->prepare("INSERT INTO users (username,password_hash,display_name,role) VALUES (?,?,?,'admin')");
$q->execute([$username, password_hash($password, PASSWORD_DEFAULT), $display]);
echo "管理者帳號已建立：{$username}\n";

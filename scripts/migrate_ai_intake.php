<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("CLI only\n"); }
$sql=file_get_contents(dirname(__DIR__).'/database/migration_ai_intake.sql');
if($sql===false)throw new RuntimeException('找不到 AI 資料庫升級檔。');
$statements=preg_split('/;\s*(?:\r?\n|$)/',$sql,-1,PREG_SPLIT_NO_EMPTY);
$host=$_ENV['DB_HOST']??'127.0.0.1';$port=$_ENV['DB_PORT']??'3306';$dbName=$_ENV['DB_NAME']??'accounting_basic';$rootUser=getenv('MYSQL_ROOT_USER')?:'root';$rootPass=getenv('MYSQL_ROOT_PASSWORD')?:'';
$pdo=new PDO("mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4",$rootUser,$rootPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach($statements as $statement){$statement=trim(preg_replace('/^\s*--.*$/m','',$statement));if($statement!=='')$pdo->exec($statement);}
echo "AI 資料表升級完成。\n";

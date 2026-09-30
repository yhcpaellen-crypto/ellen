<?php
declare(strict_types=1);
$privateEnv = dirname(__DIR__, 4) . '/.private/elle4336/.env';
if (is_file($privateEnv)) {
    putenv('APP_ENV_FILE=' . $privateEnv);
    $_ENV['APP_ENV_FILE'] = $privateEnv;
}
require __DIR__ . '/public/index.php';

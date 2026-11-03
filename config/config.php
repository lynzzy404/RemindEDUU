<?php

declare(strict_types=1);

$config = [
    'app_env' => 'development',
    'app_url' => 'http://localhost:8000',
    'data_path' => __DIR__ . '/../storage/data',
    'session_name' => 'RemindEDU_SID',
    'session_lifetime' => 7200,
    'max_upload_bytes' => 2097152,
    'max_import_rows' => 1000,
    'login_max_attempts' => 5,
    'per_page' => 10,
    'timezone' => 'Asia/Jakarta',
];

$localConfigPath = __DIR__ . '/config.local.php';
if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;
    if (is_array($localConfig)) {
        $config = array_replace($config, $localConfig);
    }
}

return $config;

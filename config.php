<?php
declare(strict_types=1);

return [
    'app_name' => 'ERMIT',
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'permitflow',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'upload_dir' => __DIR__ . '/storage/uploads',
    'max_upload_bytes' => 5 * 1024 * 1024,
    'admin_setup_key' => getenv('ADMIN_SETUP_KEY') ?: '',
];

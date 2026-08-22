<?php
declare(strict_types=1);

return [
    'app_name' => 'ERMIT',
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Manila',
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
    'openai' => [
        'api_key' => getenv('OPENAI_API_KEY') ?: '',
        'model' => getenv('OPENAI_MODEL') ?: 'gpt-5.6-luna',
        'base_url' => rtrim(getenv('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1', '/'),
        'allow_sensitive_documents' => filter_var(getenv('ALLOW_SENSITIVE_AI_SCAN') ?: 'false', FILTER_VALIDATE_BOOL),
        'daily_scan_limit' => max(1, (int) (getenv('AI_DAILY_SCAN_LIMIT') ?: 100)),
        'insight_cooldown_seconds' => max(30, (int) (getenv('AI_INSIGHT_COOLDOWN_SECONDS') ?: 60)),
    ],
];

<?php

$fileAppKey = ''; // Set this when environment variables are unavailable on your host.
$appKey = getenv('APP_KEY') ?: $fileAppKey;

return [
    'db_host' => getenv('DB_HOST') ?: 'localhost',
    'db_name' => getenv('DB_NAME') ?: 'neon_royale',
    'db_user' => getenv('DB_USER') ?: 'root',
    'db_pass' => getenv('DB_PASS') ?: '',
    'app_key' => $appKey,
    'app_name' => 'Neon Royale',
];

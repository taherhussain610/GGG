<?php

$fileAppKey = ''; // Set this when environment variables are unavailable on your host.
$appKey = getenv('APP_KEY') ?: $fileAppKey;

return [
    'db_host' => getenv('DB_HOST') ?: '',
    'db_name' => getenv('DB_NAME') ?: '',
    'db_user' => getenv('DB_USER') ?: '',
    'db_pass' => getenv('DB_PASS') ?: '',
    'app_key' => $appKey,
    'app_name' => 'Neon Royale',
];

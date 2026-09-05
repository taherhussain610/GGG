<?php

$fileAppKey = ''; // Set this when environment variables are unavailable on your host.
$fileProviderWebhookSecret = ''; // Demo/sandbox callbacks only; never commit a real provider secret.
$appKey = getenv('APP_KEY') ?: $fileAppKey;
$debug = (getenv('APP_DEBUG') === '1');

return [
    'db_host' => getenv('DB_HOST') ?: '',
    'db_name' => getenv('DB_NAME') ?: '',
    'db_user' => getenv('DB_USER') ?: '',
    'db_pass' => getenv('DB_PASS') ?: '',
    'app_key' => $appKey,
    'app_name' => 'Neon Royale',
    'debug' => $debug,
    'provider_mode' => 'demo',
    'provider_webhook_secret' => getenv('PROVIDER_WEBHOOK_SECRET') ?: $fileProviderWebhookSecret,
    'provider_webhook_window' => 300,
];

<?php
require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

json_response([
    'ok' => true,
    'provider' => provider_public_config(),
    'endpoints' => [
        'catalog' => '/provider/catalog.php',
        'launch' => '/provider/launch.php',
        'session' => '/provider/session.php',
        'wallet' => '/provider/wallet.php',
        'callback' => '/provider/callback.php',
        'webhook' => '/provider/webhook.php',
    ],
]);

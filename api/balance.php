<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$user = require_auth();
$bonusAvailable = $user['last_bonus_at'] === null
    || strtotime((string) $user['last_bonus_at']) <= time() - 86400;

json_response([
    'ok' => true,
    'balance' => (int) $user['balance'],
    'balance_label' => format_credits($user['balance']),
    'xp' => (int) $user['xp'],
    'level' => (int) $user['level'],
    'daily_bonus_available' => $bonusAvailable,
]);

<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = current_user();
if (!$user) {
    json_response(['ok' => false, 'authenticated' => false], 401);
}
json_response([
    'ok' => true,
    'authenticated' => true,
    'balance' => (int) $user['balance'],
    'balance_formatted' => fmt_coins((int) $user['balance']),
    'xp' => (int) $user['xp'],
    'level' => (int) $user['level'],
]);

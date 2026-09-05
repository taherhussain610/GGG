<?php
require __DIR__ . '/../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    require_login();
    $user = current_user();
    json_response([
        'authenticated' => true,
        'last_bonus_at' => $user['last_bonus_at'] ?? null,
    ]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false], 405);
}
require_login();
verify_csrf();
try {
    $bonus = claim_bonus((int) current_user()['id']);
    json_response(['ok' => true, 'amount' => $bonus['amount'], 'amount_formatted' => fmt_coins($bonus['amount'])]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 422);
}

<?php
require __DIR__ . '/../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user = current_user();
    if (!$user) {
        json_response(['ok' => false, 'error' => 'Authentication required'], 401);
    }
    $user = current_user();
    $last = $user['last_bonus_at'] ? strtotime((string) $user['last_bonus_at']) : 0;
    json_response([
        'ok' => true,
        'authenticated' => true,
        'last_bonus_at' => $user['last_bonus_at'] ?? null,
        'available' => $last <= time() - 86400,
        'reward' => 500 + ((int) ($user['level'] ?? 1) * 100),
    ]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false], 405);
}
if (!current_user()) {
    json_response(['ok' => false, 'error' => 'Authentication required'], 401);
}
verify_csrf();
try {
    $bonus = claim_bonus((int) current_user()['id']);
    json_response(['ok' => true, 'amount' => $bonus['amount'], 'amount_formatted' => fmt_coins($bonus['amount'])]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 422);
}

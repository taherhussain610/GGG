<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

require_post();
verify_csrf();
$user = require_auth();
rate_limit('daily-bonus-' . $user['id'], 8, 300);
$pdo = db();
$pdo->beginTransaction();

try {
    $statement = $pdo->prepare(
        'SELECT balance, level, last_bonus_at FROM users WHERE id = ? FOR UPDATE'
    );
    $statement->execute([(int) $user['id']]);
    $record = $statement->fetch();
    if ($record === false) {
        throw new DomainException('Account not found.');
    }

    $lastClaim = $record['last_bonus_at'] === null ? null : strtotime((string) $record['last_bonus_at']);
    if ($lastClaim !== null && $lastClaim > time() - 86400) {
        $nextClaim = gmdate(DATE_ATOM, $lastClaim + 86400);
        throw new DomainException('Your next daily reward unlocks at ' . $nextClaim . '.');
    }

    $reward = 500 + min(500, max(0, ((int) $record['level'] - 1) * 25));
    $newBalance = (int) $record['balance'] + $reward;
    $update = $pdo->prepare(
        'UPDATE users SET balance = ?, last_bonus_at = UTC_TIMESTAMP() WHERE id = ?'
    );
    $update->execute([$newBalance, (int) $user['id']]);
    add_wallet_entry($pdo, (int) $user['id'], $reward, 'daily_bonus');
    $pdo->commit();

    json_response([
        'ok' => true,
        'reward' => $reward,
        'message' => number_format($reward) . ' daily credits added.',
        'balance' => $newBalance,
        'balance_label' => format_credits($newBalance),
        'next_claim_at' => gmdate(DATE_ATOM, time() + 86400),
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

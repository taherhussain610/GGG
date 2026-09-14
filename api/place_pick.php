<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

require_post();
verify_csrf();
$user = require_auth();
rate_limit('sports-pick-' . $user['id'], 20, 60);
$input = request_json();

$eventId = filter_var($input['event_id'] ?? null, FILTER_VALIDATE_INT);
$stake = filter_var($input['stake'] ?? null, FILTER_VALIDATE_INT);
$selection = (string) ($input['selection'] ?? '');
if ($eventId === false || $stake === false || $stake < 10 || $stake > 100000) {
    throw new DomainException('Stake must be between 10 and 100,000 credits.');
}
if (!in_array($selection, ['home', 'draw', 'away'], true)) {
    throw new DomainException('Choose a valid prediction.');
}

$pdo = db();
$pdo->beginTransaction();

try {
    $eventStatement = $pdo->prepare(
        'SELECT id, home_odds, draw_odds, away_odds, status, starts_at
         FROM sports_events WHERE id = ? FOR UPDATE'
    );
    $eventStatement->execute([$eventId]);
    $event = $eventStatement->fetch();
    if (
        $event === false
        || $event['status'] !== 'upcoming'
        || strtotime((string) $event['starts_at']) <= time()
    ) {
        throw new DomainException('This event is no longer accepting predictions.');
    }

    $oddsField = ['home' => 'home_odds', 'draw' => 'draw_odds', 'away' => 'away_odds'][$selection];
    if ($event[$oddsField] === null) {
        throw new DomainException('That prediction is not available for this event.');
    }
    $odds = (string) $event[$oddsField];
    $scaledOdds = (int) round((float) $odds * 100);
    $potentialWin = intdiv($stake * $scaledOdds, 100);

    $userStatement = $pdo->prepare('SELECT balance, xp FROM users WHERE id = ? FOR UPDATE');
    $userStatement->execute([(int) $user['id']]);
    $wallet = $userStatement->fetch();
    if ($wallet === false || (int) $wallet['balance'] < $stake) {
        throw new DomainException('You do not have enough credits for that stake.');
    }

    $xp = max(1, min(50, intdiv($stake, 200)));
    $newXp = (int) $wallet['xp'] + $xp;
    $newBalance = (int) $wallet['balance'] - $stake;
    $updateUser = $pdo->prepare('UPDATE users SET balance = ?, xp = ?, level = ? WHERE id = ?');
    $updateUser->execute([$newBalance, $newXp, level_from_xp($newXp), (int) $user['id']]);

    $insert = $pdo->prepare(
        'INSERT INTO sports_picks(user_id, event_id, selection, odds, stake, potential_win)
         VALUES(?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([(int) $user['id'], $eventId, $selection, $odds, $stake, $potentialWin]);
    $pickId = (int) $pdo->lastInsertId();
    add_wallet_entry($pdo, (int) $user['id'], -$stake, 'sports_stake', 'sports_pick', $pickId);
    $pdo->commit();

    json_response([
        'ok' => true,
        'message' => 'Prediction placed. Potential return: ' . format_credits($potentialWin) . '.',
        'pick_id' => $pickId,
        'potential_win' => $potentialWin,
        'balance' => $newBalance,
        'balance_label' => format_credits($newBalance),
        'xp_awarded' => $xp,
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

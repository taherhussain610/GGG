<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require ROOT_PATH . '/includes/game_engine.php';

require_post();
verify_csrf();
$user = require_auth();
rate_limit('game-play-' . $user['id'], 45, 60);
$input = request_json();

$game = (string) ($input['game'] ?? '');
$bet = filter_var($input['bet'] ?? null, FILTER_VALIDATE_INT);
if (!in_array($game, ['slots', 'roulette', 'blackjack', 'dice'], true)) {
    throw new DomainException('Choose a valid game.');
}
if ($bet === false || $bet < 10 || $bet > 100000) {
    throw new DomainException('Bet must be between 10 and 100,000 credits.');
}

$pdo = db();
$pdo->beginTransaction();

try {
    $userStatement = $pdo->prepare('SELECT balance, xp FROM users WHERE id = ? FOR UPDATE');
    $userStatement->execute([(int) $user['id']]);
    $wallet = $userStatement->fetch();
    if ($wallet === false || (int) $wallet['balance'] < $bet) {
        throw new DomainException('You do not have enough credits for that bet.');
    }

    $engine = new GameEngine();
    $result = $engine->play($game, $bet, $input);

    $jackpotStatement = $pdo->prepare('SELECT balance, seed_amount FROM jackpots WHERE game = ? FOR UPDATE');
    $jackpotStatement->execute([$game]);
    $jackpot = $jackpotStatement->fetch();
    $contribution = max(1, intdiv($bet, 100));
    $jackpotWin = 0;

    if ($jackpot !== false) {
        if ($result['jackpot']) {
            $jackpotWin = (int) $jackpot['balance'] + $contribution;
            $jackpotUpdate = $pdo->prepare(
                'UPDATE jackpots
                 SET balance = seed_amount, winner_user_id = ?, won_at = UTC_TIMESTAMP()
                 WHERE game = ?'
            );
            $jackpotUpdate->execute([(int) $user['id'], $game]);
        } else {
            $jackpotUpdate = $pdo->prepare('UPDATE jackpots SET balance = balance + ? WHERE game = ?');
            $jackpotUpdate->execute([$contribution, $game]);
        }
    }

    $payout = (int) $result['payout'] + $jackpotWin;
    $xp = max(1, min(100, intdiv($bet, 100)));
    $newXp = (int) $wallet['xp'] + $xp;
    $newBalance = (int) $wallet['balance'] - $bet + $payout;

    $updateUser = $pdo->prepare('UPDATE users SET balance = ?, xp = ?, level = ? WHERE id = ?');
    $updateUser->execute([$newBalance, $newXp, level_from_xp($newXp), (int) $user['id']]);

    if ($jackpotWin > 0) {
        $result['title'] = 'Jackpot!';
        $result['message'] .= ' You also captured the ' . number_format($jackpotWin) . ' credit jackpot.';
        $result['jackpot_payout'] = $jackpotWin;
    }

    $insert = $pdo->prepare(
        "INSERT INTO game_transactions(
            user_id, game, bet, payout, result, status, xp_awarded, completed_at
         ) VALUES(?, ?, ?, ?, ?, 'complete', ?, UTC_TIMESTAMP())"
    );
    $insert->execute([
        (int) $user['id'],
        $game,
        $bet,
        $payout,
        json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        $xp,
    ]);
    $roundId = (int) $pdo->lastInsertId();

    add_wallet_entry($pdo, (int) $user['id'], -$bet, 'game_bet', 'game', $roundId);
    if ($payout > 0) {
        add_wallet_entry($pdo, (int) $user['id'], $payout, $jackpotWin > 0 ? 'game_and_jackpot_win' : 'game_payout', 'game', $roundId);
    }

    $pdo->commit();

    json_response([
        'ok' => true,
        'round_id' => $roundId,
        'result' => $result,
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

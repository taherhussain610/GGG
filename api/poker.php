<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
require ROOT_PATH . '/includes/game_engine.php';

require_post();
verify_csrf();
$user = require_auth();
rate_limit('poker-' . $user['id'], 30, 60);
$input = request_json();
$action = (string) ($input['action'] ?? '');
$pdo = db();
$engine = new GameEngine();

if ($action === 'deal') {
    $bet = filter_var($input['bet'] ?? null, FILTER_VALIDATE_INT);
    if ($bet === false || $bet < 10 || $bet > 100000) {
        throw new DomainException('Bet must be between 10 and 100,000 credits.');
    }

    $pdo->beginTransaction();
    try {
        $userStatement = $pdo->prepare('SELECT balance, xp FROM users WHERE id = ? FOR UPDATE');
        $userStatement->execute([(int) $user['id']]);
        $wallet = $userStatement->fetch();
        if ($wallet === false || (int) $wallet['balance'] < $bet) {
            throw new DomainException('You do not have enough credits for that bet.');
        }

        $pending = $pdo->prepare(
            'SELECT id FROM game_transactions
             WHERE user_id = ? AND game = "poker" AND status = "pending" LIMIT 1 FOR UPDATE'
        );
        $pending->execute([(int) $user['id']]);
        if ($pending->fetch() !== false) {
            throw new DomainException('Finish your current draw before dealing again.');
        }

        $state = $engine->newPokerState();
        $xp = max(1, min(100, intdiv($bet, 100)));
        $newXp = (int) $wallet['xp'] + $xp;
        $newBalance = (int) $wallet['balance'] - $bet;

        $updateUser = $pdo->prepare('UPDATE users SET balance = ?, xp = ?, level = ? WHERE id = ?');
        $updateUser->execute([$newBalance, $newXp, level_from_xp($newXp), (int) $user['id']]);

        $jackpot = $pdo->prepare('UPDATE jackpots SET balance = balance + ? WHERE game = "poker"');
        $jackpot->execute([max(1, intdiv($bet, 100))]);

        $insert = $pdo->prepare(
            'INSERT INTO game_transactions(user_id, game, bet, result, state_json, status, xp_awarded)
             VALUES(?, "poker", ?, "Awaiting draw", ?, "pending", ?)'
        );
        $insert->execute([
            (int) $user['id'],
            $bet,
            json_encode($state, JSON_THROW_ON_ERROR),
            $xp,
        ]);
        $roundId = (int) $pdo->lastInsertId();
        add_wallet_entry($pdo, (int) $user['id'], -$bet, 'game_bet', 'game', $roundId);
        $pdo->commit();

        json_response([
            'ok' => true,
            'stage' => 'draw',
            'round_id' => $roundId,
            'hand' => $engine->publicPokerHand($state),
            'balance' => $newBalance,
            'balance_label' => format_credits($newBalance),
            'message' => 'Choose any cards to hold, then draw once.',
        ]);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

if ($action !== 'draw') {
    throw new DomainException('Choose deal or draw.');
}

$roundId = filter_var($input['round_id'] ?? null, FILTER_VALIDATE_INT);
$holds = $input['holds'] ?? [];
if ($roundId === false || !is_array($holds)) {
    throw new DomainException('Invalid draw request.');
}

$pdo->beginTransaction();
try {
    $userStatement = $pdo->prepare('SELECT balance FROM users WHERE id = ? FOR UPDATE');
    $userStatement->execute([(int) $user['id']]);
    $wallet = $userStatement->fetch();

    $roundStatement = $pdo->prepare(
        'SELECT id, bet, state_json FROM game_transactions
         WHERE id = ? AND user_id = ? AND game = "poker" AND status = "pending"
         FOR UPDATE'
    );
    $roundStatement->execute([$roundId, (int) $user['id']]);
    $round = $roundStatement->fetch();
    if ($wallet === false || $round === false) {
        throw new DomainException('This draw is no longer available.');
    }

    try {
        $state = json_decode((string) $round['state_json'], true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new RuntimeException('Stored draw state is invalid.');
    }
    $result = $engine->finishPoker($state, $holds, (int) $round['bet']);
    $jackpotWin = 0;

    if ($result['jackpot']) {
        $jackpotStatement = $pdo->query(
            'SELECT balance, seed_amount FROM jackpots WHERE game = "poker" FOR UPDATE'
        );
        $jackpot = $jackpotStatement->fetch();
        if ($jackpot !== false) {
            $jackpotWin = (int) $jackpot['balance'];
            $updateJackpot = $pdo->prepare(
                'UPDATE jackpots
                 SET balance = seed_amount, winner_user_id = ?, won_at = UTC_TIMESTAMP()
                 WHERE game = "poker"'
            );
            $updateJackpot->execute([(int) $user['id']]);
        }
    }

    $payout = (int) $result['payout'] + $jackpotWin;
    $newBalance = (int) $wallet['balance'] + $payout;
    if ($jackpotWin > 0) {
        $result['title'] = 'Royal jackpot!';
        $result['message'] .= ' The jackpot added ' . number_format($jackpotWin) . ' credits.';
        $result['jackpot_payout'] = $jackpotWin;
    }

    $updateUser = $pdo->prepare('UPDATE users SET balance = ? WHERE id = ?');
    $updateUser->execute([$newBalance, (int) $user['id']]);
    $updateRound = $pdo->prepare(
        'UPDATE game_transactions
         SET payout = ?, result = ?, state_json = NULL, status = "complete", completed_at = UTC_TIMESTAMP()
         WHERE id = ?'
    );
    $updateRound->execute([
        $payout,
        json_encode($result, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        $roundId,
    ]);
    if ($payout > 0) {
        add_wallet_entry($pdo, (int) $user['id'], $payout, $jackpotWin > 0 ? 'game_and_jackpot_win' : 'game_payout', 'game', $roundId);
    }
    $pdo->commit();

    json_response([
        'ok' => true,
        'stage' => 'complete',
        'round_id' => $roundId,
        'result' => $result,
        'balance' => $newBalance,
        'balance_label' => format_credits($newBalance),
    ]);
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

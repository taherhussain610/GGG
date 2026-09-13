<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';
$admin = require_admin();
$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        verify_csrf();
        rate_limit('admin-action-' . $admin['id'], 30, 300);
        $action = (string) ($_POST['action'] ?? '');
        $pdo = db();

        if ($action === 'adjust_balance') {
            $targetId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            $amount = filter_var($_POST['amount'] ?? null, FILTER_VALIDATE_INT);
            $note = trim((string) ($_POST['note'] ?? ''));
            if ($targetId === false || $amount === false || $amount === 0 || abs($amount) > 1000000) {
                throw new DomainException('Enter a non-zero adjustment up to 1,000,000 credits.');
            }
            if (strlen($note) < 3 || strlen($note) > 200) {
                throw new DomainException('Add a note between 3 and 200 characters.');
            }

            $pdo->beginTransaction();
            try {
                $targetStatement = $pdo->prepare('SELECT balance FROM users WHERE id = ? FOR UPDATE');
                $targetStatement->execute([$targetId]);
                $target = $targetStatement->fetch();
                if ($target === false || (int) $target['balance'] + $amount < 0) {
                    throw new DomainException('The adjustment is invalid for this account.');
                }
                $update = $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
                $update->execute([$amount, $targetId]);
                add_wallet_entry($pdo, $targetId, $amount, 'admin_adjustment', 'admin', (int) $admin['id']);
                $audit = $pdo->prepare(
                    'INSERT INTO admin_audit(admin_user_id, action, target_type, target_id, details)
                     VALUES(?, "balance_adjustment", "user", ?, ?)'
                );
                $audit->execute([
                    (int) $admin['id'],
                    $targetId,
                    json_encode(['amount' => $amount, 'note' => $note], JSON_THROW_ON_ERROR),
                ]);
                $pdo->commit();
                flash('success', 'Virtual-credit balance adjusted.');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            redirect('/admin/');
        }

        if ($action === 'toggle_suspension') {
            $targetId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
            if ($targetId === false || $targetId === (int) $admin['id']) {
                throw new DomainException('You cannot suspend your own administrator account.');
            }
            $pdo->beginTransaction();
            try {
                $targetStatement = $pdo->prepare(
                    'SELECT is_suspended FROM users WHERE id = ? FOR UPDATE'
                );
                $targetStatement->execute([$targetId]);
                $target = $targetStatement->fetch();
                if ($target === false) {
                    throw new DomainException('Account not found.');
                }
                $newState = (int) $target['is_suspended'] === 1 ? 0 : 1;
                $update = $pdo->prepare('UPDATE users SET is_suspended = ? WHERE id = ?');
                $update->execute([$newState, $targetId]);
                $audit = $pdo->prepare(
                    'INSERT INTO admin_audit(admin_user_id, action, target_type, target_id, details)
                     VALUES(?, ?, "user", ?, ?)'
                );
                $audit->execute([
                    (int) $admin['id'],
                    $newState === 1 ? 'suspend_user' : 'restore_user',
                    $targetId,
                    json_encode(['suspended' => (bool) $newState], JSON_THROW_ON_ERROR),
                ]);
                $pdo->commit();
                flash('success', $newState === 1 ? 'Account suspended.' : 'Account restored.');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            redirect('/admin/');
        }

        if ($action === 'settle_event') {
            $eventId = filter_var($_POST['event_id'] ?? null, FILTER_VALIDATE_INT);
            $winner = (string) ($_POST['winner'] ?? '');
            $homeScore = filter_var($_POST['home_score'] ?? null, FILTER_VALIDATE_INT);
            $awayScore = filter_var($_POST['away_score'] ?? null, FILTER_VALIDATE_INT);
            if (
                $eventId === false
                || !in_array($winner, ['home', 'draw', 'away'], true)
                || $homeScore === false
                || $awayScore === false
                || $homeScore < 0
                || $awayScore < 0
                || $homeScore > 999
                || $awayScore > 999
            ) {
                throw new DomainException('Enter a valid winner and final score.');
            }

            $pdo->beginTransaction();
            try {
                $eventStatement = $pdo->prepare(
                    'SELECT status FROM sports_events WHERE id = ? FOR UPDATE'
                );
                $eventStatement->execute([$eventId]);
                $event = $eventStatement->fetch();
                if ($event === false || $event['status'] === 'finished' || $event['status'] === 'cancelled') {
                    throw new DomainException('This event cannot be settled again.');
                }

                $pickStatement = $pdo->prepare(
                    'SELECT id, user_id, selection, potential_win
                     FROM sports_picks
                     WHERE event_id = ? AND status = "open"
                     ORDER BY user_id, id
                     FOR UPDATE'
                );
                $pickStatement->execute([$eventId]);
                $picks = $pickStatement->fetchAll();

                $eventUpdate = $pdo->prepare(
                    'UPDATE sports_events
                     SET status = "finished", result_selection = ?, home_score = ?, away_score = ?
                     WHERE id = ?'
                );
                $eventUpdate->execute([$winner, $homeScore, $awayScore, $eventId]);

                foreach ($picks as $pick) {
                    $won = $pick['selection'] === $winner;
                    if ($won) {
                        $lockUser = $pdo->prepare('SELECT id FROM users WHERE id = ? FOR UPDATE');
                        $lockUser->execute([(int) $pick['user_id']]);
                        $credit = $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
                        $credit->execute([(int) $pick['potential_win'], (int) $pick['user_id']]);
                        add_wallet_entry(
                            $pdo,
                            (int) $pick['user_id'],
                            (int) $pick['potential_win'],
                            'sports_payout',
                            'sports_pick',
                            (int) $pick['id']
                        );
                    }
                    $settlePick = $pdo->prepare(
                        'UPDATE sports_picks SET status = ?, settled_at = UTC_TIMESTAMP() WHERE id = ?'
                    );
                    $settlePick->execute([$won ? 'won' : 'lost', (int) $pick['id']]);
                }

                $audit = $pdo->prepare(
                    'INSERT INTO admin_audit(admin_user_id, action, target_type, target_id, details)
                     VALUES(?, "settle_event", "sports_event", ?, ?)'
                );
                $audit->execute([
                    (int) $admin['id'],
                    $eventId,
                    json_encode([
                        'winner' => $winner,
                        'score' => [$homeScore, $awayScore],
                        'picks_settled' => count($picks),
                    ], JSON_THROW_ON_ERROR),
                ]);
                $pdo->commit();
                flash('success', 'Event settled and winning predictions credited.');
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $exception;
            }
            redirect('/admin/');
        }

        throw new DomainException('Unknown administrator action.');
    } catch (DomainException $exception) {
        $error = $exception->getMessage();
    }
}

$userStats = db()->query(
    'SELECT COUNT(*) AS users, COALESCE(SUM(balance), 0) AS balances,
            SUM(is_suspended = 1) AS suspended
     FROM users'
)->fetch();
$gameStats = db()->query(
    'SELECT COUNT(*) AS rounds, COALESCE(SUM(bet), 0) AS staked,
            COALESCE(SUM(payout), 0) AS paid
     FROM game_transactions WHERE status = "complete"'
)->fetch();
$bonusTotal = (int) db()->query(
    'SELECT COALESCE(SUM(amount), 0) FROM wallet_transactions WHERE type = "daily_bonus"'
)->fetchColumn();
$users = db()->query(
    'SELECT id, username, email, balance, xp, level, is_admin, is_suspended, created_at
     FROM users ORDER BY created_at DESC LIMIT 50'
)->fetchAll();
$events = db()->query(
    'SELECT id, sport, league, home_team, away_team, starts_at, status
     FROM sports_events WHERE status IN ("upcoming", "live")
     ORDER BY starts_at LIMIT 30'
)->fetchAll();
$gameBreakdown = db()->query(
    'SELECT game, COUNT(*) AS rounds, COALESCE(SUM(bet), 0) AS staked,
            COALESCE(SUM(payout), 0) AS paid
     FROM game_transactions WHERE status = "complete"
     GROUP BY game ORDER BY rounds DESC'
)->fetchAll();
$audits = db()->query(
    'SELECT a.action, a.target_type, a.target_id, a.details, a.created_at, u.username
     FROM admin_audit a JOIN users u ON u.id = a.admin_user_id
     ORDER BY a.created_at DESC LIMIT 20'
)->fetchAll();

$pageTitle = 'Admin dashboard';
$activePage = 'admin';
require ROOT_PATH . '/partials/header.php';
?>
<section class="page-shell page-intro split-intro">
    <div>
        <p class="eyebrow">Restricted operations</p>
        <h1>Admin dashboard</h1>
        <p>Manage virtual balances and settle simulated events. Every mutation is audited.</p>
    </div>
    <span class="fair-badge">Administrator</span>
</section>

<?php if ($error !== ''): ?>
    <div class="page-shell form-error" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<section class="page-shell stat-grid">
    <div class="stat-card"><span>Registered players</span><strong><?= number_format((int) $userStats['users']) ?></strong></div>
    <div class="stat-card"><span>Credits in wallets</span><strong><?= e(format_credits($userStats['balances'])) ?></strong></div>
    <div class="stat-card"><span>Completed rounds</span><strong><?= number_format((int) $gameStats['rounds']) ?></strong></div>
    <div class="stat-card"><span>Daily bonuses issued</span><strong><?= e(format_credits($bonusTotal)) ?></strong></div>
</section>

<section class="page-shell admin-section panel">
    <div class="section-heading compact">
        <div><p class="eyebrow">Accounts</p><h2>Recent users</h2></div>
        <span class="muted"><?= (int) $userStats['suspended'] ?> suspended</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
            <tr><th>User</th><th>Balance</th><th>Level</th><th>Adjustment</th><th>Access</th></tr>
            </thead>
            <tbody>
            <?php foreach ($users as $account): ?>
                <tr>
                    <td><strong><?= e($account['username']) ?></strong><small><?= e($account['email']) ?></small></td>
                    <td><?= e(format_credits($account['balance'])) ?></td>
                    <td><?= (int) $account['level'] ?> <small><?= number_format((int) $account['xp']) ?> XP</small></td>
                    <td>
                        <form method="post" class="admin-inline-form">
                            <?= csrf_input() ?>
                            <input type="hidden" name="action" value="adjust_balance">
                            <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                            <input type="number" name="amount" min="-1000000" max="1000000" placeholder="+ / −" required aria-label="Credit adjustment">
                            <input name="note" minlength="3" maxlength="200" placeholder="Reason" required aria-label="Adjustment reason">
                            <button class="button button-secondary button-small" type="submit">Apply</button>
                        </form>
                    </td>
                    <td>
                        <?php if ((int) $account['id'] === (int) $admin['id']): ?>
                            <span class="status-badge">Current admin</span>
                        <?php else: ?>
                            <form method="post">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="toggle_suspension">
                                <input type="hidden" name="user_id" value="<?= (int) $account['id'] ?>">
                                <button class="button button-ghost button-small" type="submit">
                                    <?= (int) $account['is_suspended'] === 1 ? 'Restore' : 'Suspend' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="page-shell admin-grid">
    <div class="panel admin-section">
        <div class="section-heading compact"><div><p class="eyebrow">Simulation</p><h2>Settle events</h2></div></div>
        <?php if ($events === []): ?>
            <p class="empty-state">No open events.</p>
        <?php endif; ?>
        <?php foreach ($events as $event): ?>
            <form method="post" class="settlement-form">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="settle_event">
                <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                <div>
                    <strong><?= e($event['home_team']) ?> vs <?= e($event['away_team']) ?></strong>
                    <small><?= e($event['sport']) ?> · <?= e($event['league']) ?></small>
                </div>
                <select name="winner" required aria-label="Winning selection">
                    <option value="home">Home</option>
                    <option value="draw">Draw</option>
                    <option value="away">Away</option>
                </select>
                <input type="number" name="home_score" min="0" max="999" value="1" required aria-label="Home score">
                <span>:</span>
                <input type="number" name="away_score" min="0" max="999" value="0" required aria-label="Away score">
                <button class="button button-primary button-small" type="submit">Settle</button>
            </form>
        <?php endforeach; ?>
    </div>

    <div class="panel admin-section">
        <div class="section-heading compact"><div><p class="eyebrow">Game metrics</p><h2>Performance</h2></div></div>
        <div class="history-list">
            <?php foreach ($gameBreakdown as $row): ?>
                <div class="history-row">
                    <span class="history-icon"><?= e(strtoupper(substr($row['game'], 0, 1))) ?></span>
                    <div><strong><?= e(ucfirst($row['game'])) ?></strong><small><?= number_format((int) $row['rounds']) ?> rounds</small></div>
                    <span><small>Played <?= e(format_credits($row['staked'])) ?></small><br>Paid <?= e(format_credits($row['paid'])) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="page-shell admin-section panel">
    <div class="section-heading compact"><div><p class="eyebrow">Immutable trail</p><h2>Recent admin activity</h2></div></div>
    <div class="history-list">
        <?php foreach ($audits as $audit): ?>
            <div class="history-row">
                <span class="history-icon">A</span>
                <div><strong><?= e(ucwords(str_replace('_', ' ', $audit['action']))) ?></strong><small>by <?= e($audit['username']) ?></small></div>
                <time><?= e(gmdate('M j, H:i', strtotime((string) $audit['created_at']))) ?> UTC</time>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php require ROOT_PATH . '/partials/footer.php'; ?>

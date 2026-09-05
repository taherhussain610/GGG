<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $bonus = claim_bonus((int) current_user()['id']);
        flash('Daily bonus claimed: ' . fmt_coins($bonus['amount']) . '.', 'success');
    } catch (Throwable $e) {
        flash($e->getMessage(), 'danger');
    }
    header('Location: /profile.php');
    exit;
}

$user = current_user();
$history = user_history((int) $user['id']);
$summary = $history['summary'];
$progress = (int) round((level_progress((int) $user['xp']) / 500) * 100);
render_header('Profile', 'profile');
?>
<section class="profile-grid">
    <section>
        <span class="badge">Profile</span>
        <h1><?= e($user['username']) ?></h1>
        <p class="muted"><?= e($user['email']) ?></p>
        <div class="stats-grid">
            <article class="stat-card"><h3><?= fmt_coins((int) $user['balance']) ?></h3><p class="muted">Balance</p></article>
            <article class="stat-card"><h3><?= number_format((int) $user['xp']) ?></h3><p class="muted">XP</p></article>
            <article class="stat-card"><h3><?= (int) $user['level'] ?></h3><p class="muted">Level</p></article>
            <article class="stat-card"><h3><?= $user['last_bonus_at'] ? e($user['last_bonus_at']) : 'Ready' ?></h3><p class="muted">Bonus</p></article>
        </div>
        <div class="stats-grid compact-stats">
            <article class="stat-card"><h3><?= fmt_coins((int) $summary['total_bet']) ?></h3><p class="muted">Total bets</p></article>
            <article class="stat-card"><h3><?= fmt_coins((int) $summary['total_payout']) ?></h3><p class="muted">Total payouts</p></article>
            <article class="stat-card"><h3><?= number_format((int) $summary['open_picks']) ?></h3><p class="muted">Open picks</p></article>
            <?php $netSwing = (int) $summary['total_payout'] - (int) $summary['total_bet']; ?>
            <article class="stat-card"><h3><?= $netSwing > 0 ? '+' : ($netSwing < 0 ? '-' : '') ?><?= fmt_coins(abs($netSwing)) ?></h3><p class="muted">Net swing</p></article>
        </div>
        <p class="muted">Progress to next level</p>
        <div class="progress-bar"><span style="width: <?= $progress ?>%"></span></div>
        <div class="toolbar toolbar-tabs" style="margin-top:1rem;">
            <a class="mini-tab active" href="/profile.php">Profile</a>
            <a class="mini-tab" href="/daily_bonus.php">Daily bonus</a>
            <a class="mini-tab" href="/leaderboard.php">Leaderboard</a>
        </div>
        <div class="bonus-card" style="margin-top:1rem;">
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <button type="submit">Claim daily bonus</button>
            </form>
        </div>
    </section>
    <section class="table-wrap">
        <h2>Game history</h2>
        <table>
            <thead><tr><th>Game</th><th>Bet</th><th>Payout</th><th>Result</th></tr></thead>
            <tbody>
                <?php if (!$history['games']): ?>
                    <tr><td colspan="4" class="muted">No game history yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($history['games'] as $row): ?>
                        <tr>
                            <td><?= e(ucfirst($row['game'])) ?></td>
                            <td><?= fmt_coins((int) $row['bet']) ?></td>
                            <td><?= fmt_coins((int) $row['payout']) ?></td>
                            <td><?= e($row['result']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <h2 style="margin-top:1rem;">Sports picks</h2>
        <table>
            <thead><tr><th>Event</th><th>Pick</th><th>Stake</th><th>Status</th></tr></thead>
            <tbody>
                <?php if (!$history['picks']): ?>
                    <tr><td colspan="4" class="muted">No sports picks yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($history['picks'] as $row): ?>
                        <tr>
                            <td><?= e($row['sport']) ?> · <?= e($row['home_team']) ?> vs <?= e($row['away_team']) ?></td>
                            <td><?= e(strtoupper($row['selection'])) ?></td>
                            <td><?= fmt_coins((int) $row['stake']) ?></td>
                            <td><?= e(ucfirst($row['status'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </section>
</section>
<?php render_footer('profile'); ?>

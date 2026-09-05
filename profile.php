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
        <p class="muted">Progress to next level</p>
        <div class="progress-bar"><span style="width: <?= $progress ?>%"></span></div>
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
                <?php foreach ($history['games'] as $row): ?>
                    <tr>
                        <td><?= e(ucfirst($row['game'])) ?></td>
                        <td><?= fmt_coins((int) $row['bet']) ?></td>
                        <td><?= fmt_coins((int) $row['payout']) ?></td>
                        <td><?= e($row['result']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <h2 style="margin-top:1rem;">Sports picks</h2>
        <table>
            <thead><tr><th>Event</th><th>Pick</th><th>Stake</th><th>Status</th></tr></thead>
            <tbody>
                <?php foreach ($history['picks'] as $row): ?>
                    <tr>
                        <td><?= e($row['sport']) ?> · <?= e($row['home_team']) ?> vs <?= e($row['away_team']) ?></td>
                        <td><?= e(strtoupper($row['selection'])) ?></td>
                        <td><?= fmt_coins((int) $row['stake']) ?></td>
                        <td><?= e(ucfirst($row['status'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</section>
<?php render_footer('profile'); ?>

<?php
require __DIR__ . '/includes/bootstrap.php';
$rows = leaderboard_rows();
$dashboard = dashboard_snapshot();
render_header('Leaderboard', 'leaderboard');
?>
<section class="stats-grid compact-stats">
    <article class="stat-card"><span class="badge">Players</span><h3><?= number_format($dashboard['players']) ?></h3><p class="muted">Profiles tracked</p></article>
    <article class="stat-card"><span class="badge">Top Wallet</span><h3><?= fmt_coins($dashboard['top_balance']) ?></h3><p class="muted">Highest balance</p></article>
    <article class="stat-card"><span class="badge">Jackpot</span><h3><?= fmt_coins($dashboard['top_jackpot']) ?></h3><p class="muted">Largest pool</p></article>
    <article class="stat-card"><span class="badge">Open Picks</span><h3><?= number_format($dashboard['open_picks']) ?></h3><p class="muted">Pending slips</p></article>
</section>
<section class="panel table-wrap">
    <div class="section-head">
        <div>
            <span class="badge">Leaderboard</span>
            <h1>Top balances and XP</h1>
        </div>
    </div>
    <table>
        <thead><tr><th>#</th><th>Player</th><th>Balance</th><th>XP</th><th>Level</th><th>Joined</th></tr></thead>
        <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="6" class="muted">No leaderboard entries yet.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $index => $row): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= e($row['username']) ?></td>
                        <td><?= fmt_coins((int) $row['balance']) ?></td>
                        <td><?= number_format((int) $row['xp']) ?></td>
                        <td><?= (int) $row['level'] ?></td>
                        <td><?= e((string) $row['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</section>
<?php render_footer('leaderboard'); ?>

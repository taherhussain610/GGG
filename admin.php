<?php
require __DIR__ . '/includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    settle_sports();
    flash('Sports feed settled successfully.', 'success');
    header('Location: /admin.php');
    exit;
}

$data = admin_snapshot();
$stats = $data['stats'];
render_header('Admin', 'admin');
?>
<section class="panel">
    <div class="section-head">
        <div>
            <span class="badge">Admin</span>
            <h1>Balances, bonuses and virtual game activity</h1>
        </div>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <button type="submit" class="secondary">Settle sports feed</button>
        </form>
    </div>
    <div class="stats-grid">
        <article class="stat-card"><h3><?= number_format((int) ($stats['users_total'] ?? 0)) ?></h3><p class="muted">Users</p></article>
        <article class="stat-card"><h3><?= fmt_coins((int) ($stats['balances_total'] ?? 0)) ?></h3><p class="muted">Wallets</p></article>
        <article class="stat-card"><h3><?= fmt_coins((int) ($stats['bet_total'] ?? 0)) ?></h3><p class="muted">Virtual bets</p></article>
        <article class="stat-card"><h3><?= fmt_coins((int) ($stats['payout_total'] ?? 0)) ?></h3><p class="muted">Virtual payouts</p></article>
    </div>
</section>
<section class="panel table-wrap">
    <h2>Recent users</h2>
    <table>
        <thead><tr><th>User</th><th>Email</th><th>Balance</th><th>XP</th><th>Level</th><th>Admin</th><th>Last bonus</th></tr></thead>
        <tbody>
            <?php foreach ($data['users'] as $row): ?>
                <tr>
                    <td><?= e($row['username']) ?></td>
                    <td><?= e($row['email']) ?></td>
                    <td><?= fmt_coins((int) $row['balance']) ?></td>
                    <td><?= number_format((int) $row['xp']) ?></td>
                    <td><?= (int) $row['level'] ?></td>
                    <td><?= (int) $row['is_admin'] === 1 ? 'Yes' : 'No' ?></td>
                    <td><?= $row['last_bonus_at'] ? e($row['last_bonus_at']) : 'Never' ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php render_footer('admin'); ?>

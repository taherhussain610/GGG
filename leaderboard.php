<?php
require __DIR__ . '/includes/bootstrap.php';
$rows = leaderboard_rows();
render_header('Leaderboard', 'leaderboard');
?>
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
        </tbody>
    </table>
</section>
<?php render_footer('leaderboard'); ?>

<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$statement = db()->query(
    'SELECT id, username, xp, level
     FROM users
     WHERE is_suspended = 0
     ORDER BY xp DESC, created_at ASC
     LIMIT 50'
);
$leaders = $statement->fetchAll();
$viewer = current_user();
$viewerRank = null;
if ($viewer !== null) {
    $rankStatement = db()->prepare(
        'SELECT COUNT(*) + 1 FROM users
         WHERE is_suspended = 0 AND (xp > ? OR (xp = ? AND created_at < ?))'
    );
    $rankStatement->execute([(int) $viewer['xp'], (int) $viewer['xp'], $viewer['created_at']]);
    $viewerRank = (int) $rankStatement->fetchColumn();
}

$pageTitle = 'Leaderboard';
$activePage = 'leaders';
require ROOT_PATH . '/partials/header.php';
?>
<section class="page-shell page-intro split-intro">
    <div>
        <p class="eyebrow">All-time XP</p>
        <h1>Leaderboard</h1>
        <p>Ranks use experience points only. Private balances and activity stay private.</p>
    </div>
    <?php if ($viewerRank !== null): ?>
        <div class="rank-summary"><span>Your rank</span><strong>#<?= $viewerRank ?></strong></div>
    <?php endif; ?>
</section>
<section class="page-shell leaderboard">
    <?php if ($leaders === []): ?>
        <p class="empty-state">The leaderboard is waiting for its first player.</p>
    <?php endif; ?>
    <?php foreach ($leaders as $index => $leader): ?>
        <article class="leader-row<?= $viewer !== null && (int) $leader['id'] === (int) $viewer['id'] ? ' is-you' : '' ?>">
            <span class="leader-rank"><?= $index + 1 ?></span>
            <span class="avatar" aria-hidden="true"><?= e(strtoupper(substr($leader['username'], 0, 1))) ?></span>
            <div><strong><?= e($leader['username']) ?></strong><small>Level <?= (int) $leader['level'] ?></small></div>
            <strong><?= number_format((int) $leader['xp']) ?> XP</strong>
        </article>
    <?php endforeach; ?>
</section>
<?php require ROOT_PATH . '/partials/footer.php'; ?>

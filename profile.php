<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
$user = require_auth();

$gameStatement = db()->prepare(
    'SELECT game, bet, payout, result, status, created_at
     FROM game_transactions WHERE user_id = ?
     ORDER BY created_at DESC LIMIT 20'
);
$gameStatement->execute([(int) $user['id']]);
$games = $gameStatement->fetchAll();

$walletStatement = db()->prepare(
    'SELECT amount, type, created_at
     FROM wallet_transactions WHERE user_id = ?
     ORDER BY created_at DESC LIMIT 30'
);
$walletStatement->execute([(int) $user['id']]);
$walletHistory = $walletStatement->fetchAll();

$statsStatement = db()->prepare(
    "SELECT COUNT(*) AS rounds, COALESCE(SUM(bet), 0) AS played,
            COALESCE(SUM(payout), 0) AS returned
     FROM game_transactions WHERE user_id = ? AND status = 'complete'"
);
$statsStatement->execute([(int) $user['id']]);
$stats = $statsStatement->fetch();

$pageTitle = 'Your profile';
$activePage = 'profile';
require ROOT_PATH . '/partials/header.php';
?>
<section class="page-shell profile-hero">
    <span class="avatar avatar-large" aria-hidden="true"><?= e(strtoupper(substr($user['username'], 0, 1))) ?></span>
    <div>
        <p class="eyebrow">Player profile</p>
        <h1><?= e($user['username']) ?></h1>
        <p>Member since <?= e(gmdate('F Y', strtotime((string) $user['created_at']))) ?></p>
    </div>
    <div class="level-progress">
        <div><span>Level <?= (int) $user['level'] ?></span><strong><?= number_format((int) $user['xp']) ?> XP</strong></div>
        <progress max="1000" value="<?= (int) $user['xp'] % 1000 ?>" aria-label="Progress to next level">Level progress</progress>
        <small><?= number_format(1000 - ((int) $user['xp'] % 1000)) ?> XP to next level</small>
    </div>
</section>

<section class="page-shell stat-grid">
    <div class="stat-card"><span>Virtual balance</span><strong data-balance><?= e(format_credits($user['balance'])) ?></strong></div>
    <div class="stat-card"><span>Completed rounds</span><strong><?= number_format((int) ($stats['rounds'] ?? 0)) ?></strong></div>
    <div class="stat-card"><span>Credits played</span><strong><?= e(format_credits($stats['played'] ?? 0)) ?></strong></div>
    <div class="stat-card"><span>Credits returned</span><strong><?= e(format_credits($stats['returned'] ?? 0)) ?></strong></div>
</section>

<section class="page-shell profile-columns">
    <div class="panel">
        <div class="section-heading compact"><div><p class="eyebrow">Recent play</p><h2>Game history</h2></div></div>
        <?php if ($games === []): ?>
            <p class="empty-state">No rounds yet. <a href="<?= e(url('/casino.php')) ?>">Choose a game</a>.</p>
        <?php else: ?>
            <div class="history-list">
                <?php foreach ($games as $round): ?>
                    <?php
                    $result = [];
                    try {
                        $result = json_decode((string) $round['result'], true, 16, JSON_THROW_ON_ERROR);
                    } catch (JsonException) {
                        $result = ['title' => $round['result']];
                    }
                    ?>
                    <div class="history-row">
                        <span class="history-icon"><?= e(strtoupper(substr($round['game'], 0, 1))) ?></span>
                        <div>
                            <strong><?= e(ucfirst($round['game'])) ?> · <?= e($result['title'] ?? ucfirst($round['status'])) ?></strong>
                            <small><?= e(gmdate('M j, H:i', strtotime((string) $round['created_at']))) ?> UTC</small>
                        </div>
                        <span class="<?= (int) $round['payout'] > (int) $round['bet'] ? 'positive' : 'muted' ?>">
                            <?= (int) $round['payout'] > 0 ? '+' : '' ?><?= e(format_credits($round['payout'])) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="section-heading compact"><div><p class="eyebrow">Ledger</p><h2>Balance activity</h2></div></div>
        <div class="history-list">
            <?php foreach ($walletHistory as $entry): ?>
                <div class="history-row">
                    <span class="history-icon"><?= (int) $entry['amount'] >= 0 ? '+' : '−' ?></span>
                    <div>
                        <strong><?= e(ucwords(str_replace('_', ' ', $entry['type']))) ?></strong>
                        <small><?= e(gmdate('M j, H:i', strtotime((string) $entry['created_at']))) ?> UTC</small>
                    </div>
                    <span class="<?= (int) $entry['amount'] >= 0 ? 'positive' : 'negative' ?>">
                        <?= (int) $entry['amount'] > 0 ? '+' : '' ?><?= e(format_credits($entry['amount'])) ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require ROOT_PATH . '/partials/footer.php'; ?>

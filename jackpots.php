<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$statement = db()->query(
    'SELECT j.game, j.balance, j.seed_amount, j.won_at, u.username AS winner
     FROM jackpots j
     LEFT JOIN users u ON u.id = j.winner_user_id
     ORDER BY j.balance DESC'
);
$jackpots = $statement->fetchAll();
$gameNames = [
    'slots' => 'Nova Reels',
    'roulette' => 'Orbit Roulette',
    'blackjack' => 'Midnight 21',
    'dice' => 'Pulse Dice',
    'poker' => 'Neon Draw',
];

$pageTitle = 'Jackpots';
$activePage = 'rewards';
require ROOT_PATH . '/partials/header.php';
?>
<section class="page-shell page-intro">
    <p class="eyebrow">Growing virtual prize pools</p>
    <h1>Jackpots</h1>
    <p>One percent of each virtual game stake grows its game's pot. Published trigger conditions can win it.</p>
</section>
<section class="page-shell jackpot-grid">
    <?php foreach ($jackpots as $jackpot): ?>
        <article class="jackpot-card">
            <span><?= e($gameNames[$jackpot['game']] ?? ucfirst($jackpot['game'])) ?></span>
            <strong><?= e(format_credits($jackpot['balance'])) ?></strong>
            <progress max="100"
                      value="<?= min(100, (int) round(((int) $jackpot['balance'] / max(1, (int) $jackpot['seed_amount'])) * 35)) ?>"
                      aria-label="<?= e(($gameNames[$jackpot['game']] ?? ucfirst($jackpot['game'])) . ' jackpot growth') ?>">
                Jackpot growth
            </progress>
            <?php if ($jackpot['winner'] !== null): ?>
                <small>Last won by <?= e($jackpot['winner']) ?> · <?= e(gmdate('M j', strtotime((string) $jackpot['won_at']))) ?></small>
            <?php else: ?>
                <small>Waiting for its first winner</small>
            <?php endif; ?>
            <a class="button button-secondary" href="<?= e(url('/games/' . $jackpot['game'] . '.php')) ?>">Play game</a>
        </article>
    <?php endforeach; ?>
</section>
<?php require ROOT_PATH . '/partials/footer.php'; ?>

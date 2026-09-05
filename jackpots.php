<?php
$title = 'Jackpots';
$activeNav = 'casino';
require __DIR__ . '/partials/header.php';
$jackpots = jackpot_totals();
?>
<section class="panel">
    <span class="badge">Jackpots</span>
    <h1>Virtual jackpots</h1>
    <p class="muted">Pools are derived from play-money activity and remain virtual-credit only.</p>
</section>
<section class="promo-grid">
    <?php foreach ($jackpots as $name => $value): ?>
        <article class="stat-card">
            <h2><?= e($name) ?></h2>
            <strong><?= fmt_coins($value) ?></strong>
        </article>
    <?php endforeach; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>

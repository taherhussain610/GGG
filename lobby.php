<?php
$title = 'Casino';
$activeNav = 'casino';
require __DIR__ . '/partials/header.php';
$games = game_definitions();
?>
<section class="section-head">
    <div>
        <span class="badge">Lobby</span>
        <h1>Casino lobby</h1>
        <p class="muted">Compact categories inspired by modern casino lobby patterns.</p>
    </div>
</section>
<section class="game-grid">
    <?php foreach ($games as $key => $game): ?>
        <article class="game-card">
            <span class="badge"><?= e(ucfirst($game['category'])) ?></span>
            <h3><?= e($game['label']) ?></h3>
            <p class="muted"><?= e($game['description']) ?></p>
            <a class="button-link" href="/games/<?= e($key) ?>.php">Open game</a>
        </article>
    <?php endforeach; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>

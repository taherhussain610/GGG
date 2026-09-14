<?php

declare(strict_types=1);
?>
</main>

<footer class="site-footer">
    <div>
        <a class="brand brand-small" href="<?= e(url('/')) ?>">
            <span class="brand-mark" aria-hidden="true">NR</span>
            <span>NEON ROYALE</span>
        </a>
        <p>Virtual entertainment credits only. No purchases, prizes, or cash-out.</p>
    </div>
    <nav aria-label="Legal and help">
        <a href="<?= e(url('/responsible-play.php')) ?>">Healthy play</a>
        <a href="<?= e(url('/terms.php')) ?>">Terms</a>
        <a href="<?= e(url('/privacy.php')) ?>">Privacy</a>
    </nav>
    <p>© <?= date('Y') ?> Neon Royale</p>
</footer>

<nav class="mobile-nav" aria-label="Mobile navigation">
    <?php
    $mobileItems = [
        'casino' => ['/casino.php', '◇', 'Casino'],
        'sports' => ['/sports.php', '◫', 'Sports'],
        'results' => ['/results.php', '✓', 'Results'],
        'rewards' => ['/bonus.php', '✦', 'Rewards'],
        'profile' => [$viewer === null ? '/login.php' : '/profile.php', '○', 'Profile'],
    ];
    foreach ($mobileItems as $key => [$path, $icon, $label]):
    ?>
        <a href="<?= e(url($path)) ?>"<?= $activePage === $key ? ' aria-current="page"' : '' ?>>
            <span aria-hidden="true"><?= e($icon) ?></span>
            <?= e($label) ?>
        </a>
    <?php endforeach; ?>
</nav>
</body>
</html>

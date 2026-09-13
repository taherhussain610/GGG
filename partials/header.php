<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Play with virtual credits';
$activePage = $activePage ?? '';
$viewer = current_user();
$navItems = [
    'casino' => ['/casino.php', 'Casino'],
    'sports' => ['/sports.php', 'Sports'],
    'results' => ['/results.php', 'Results'],
    'rewards' => ['/bonus.php', 'Rewards'],
    'leaders' => ['/leaderboard.php', 'Leaders'],
];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#080b14">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="description" content="Neon Royale is a free play-money casino with virtual credits and no cash prizes.">
    <title><?= e($pageTitle) ?> · Neon Royale</title>
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
    <script src="<?= e(url('/assets/js/app.js')) ?>" defer></script>
</head>
<body data-authenticated="<?= $viewer === null ? 'false' : 'true' ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<div class="notice-bar">
    <span aria-hidden="true">◆</span>
    Free play only. Credits have no monetary value and cannot be purchased or withdrawn.
</div>
<header class="site-header">
    <a class="brand" href="<?= e(url('/')) ?>" aria-label="Neon Royale home">
        <span class="brand-mark" aria-hidden="true">NR</span>
        <span>NEON <strong>ROYALE</strong></span>
    </a>

    <nav class="desktop-nav" aria-label="Primary navigation">
        <?php foreach ($navItems as $key => [$path, $label]): ?>
            <a href="<?= e(url($path)) ?>"<?= $activePage === $key ? ' aria-current="page"' : '' ?>>
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="account-actions">
        <?php if ($viewer !== null): ?>
            <a class="balance-pill" href="<?= e(url('/profile.php')) ?>" aria-label="View profile and balance">
                <span class="status-dot" aria-hidden="true"></span>
                <span data-balance><?= e(format_credits($viewer['balance'])) ?></span>
            </a>
            <?php if ((int) $viewer['is_admin'] === 1): ?>
                <a class="icon-button" href="<?= e(url('/admin/')) ?>" aria-label="Admin dashboard">⚙</a>
            <?php endif; ?>
            <form method="post" action="<?= e(url('/logout.php')) ?>" class="inline-form">
                <?= csrf_input() ?>
                <button class="button button-ghost button-small" type="submit">Log out</button>
            </form>
        <?php else: ?>
            <a class="button button-ghost button-small" href="<?= e(url('/login.php')) ?>">Log in</a>
            <a class="button button-primary button-small" href="<?= e(url('/register.php')) ?>">Play free</a>
        <?php endif; ?>
    </div>
</header>

<?php foreach (pull_flashes() as $flash): ?>
    <div class="flash flash-<?= e($flash['type'] ?? 'info') ?>" role="status">
        <?= e($flash['message'] ?? '') ?>
    </div>
<?php endforeach; ?>

<main id="main-content">

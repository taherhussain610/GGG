<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

function play_tx(string $game, int $bet, array $input = []): array
{
    return play_game((int) current_user()['id'], $game, $bet, $input);
}

function render_game_page(string $title, string $activeGame, string $message, string $error, callable $content): void
{
    $GLOBALS['title'] = $title;
    $GLOBALS['activeNav'] = 'casino';
    require __DIR__ . '/../partials/header.php';
    ?>
    <section class="panel form">
        <span class="badge">Game</span>
        <h1><?= e($title) ?></h1>
        <?php if ($message !== ''): ?><div class="banner banner-success"><?= e($message) ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="banner banner-danger"><?= e($error) ?></div><?php endif; ?>
        <?php $content($activeGame); ?>
    </section>
    <?php
    require __DIR__ . '/../partials/footer.php';
}

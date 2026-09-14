<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$games = [
    [
        'key' => 'slots',
        'title' => 'Nova Reels',
        'description' => 'Three reels, bright symbols, instant results.',
        'category' => 'slots',
        'tag' => 'Popular',
        'icon' => '✦',
        'accent' => 'purple',
    ],
    [
        'key' => 'roulette',
        'title' => 'Orbit Roulette',
        'description' => 'A transparent single-zero virtual wheel.',
        'category' => 'table',
        'tag' => 'Classic',
        'icon' => '17',
        'accent' => 'red',
    ],
    [
        'key' => 'blackjack',
        'title' => 'Midnight 21',
        'description' => 'Quick, server-dealt blackjack rounds.',
        'category' => 'cards',
        'tag' => 'Cards',
        'icon' => 'A♠',
        'accent' => 'cyan',
    ],
    [
        'key' => 'dice',
        'title' => 'Pulse Dice',
        'description' => 'Call high or low before the dice land.',
        'category' => 'quick',
        'tag' => 'Fast',
        'icon' => '⚄',
        'accent' => 'gold',
    ],
    [
        'key' => 'poker',
        'title' => 'Neon Draw',
        'description' => 'Hold your best cards and draw once.',
        'category' => 'cards',
        'tag' => 'Strategy',
        'icon' => 'K♦',
        'accent' => 'pink',
    ],
];

$legacyCategories = ['196' => 'slots', '189' => 'table', '188' => 'cards', '1023' => 'quick'];
$selectedCategory = $legacyCategories[(string) ($_GET['category'] ?? '')] ?? 'all';

$jackpots = [];
try {
    $jackpots = db()->query('SELECT game, balance FROM jackpots ORDER BY balance DESC')->fetchAll();
} catch (PDOException) {
    // The game grid remains useful while a fresh installation is being configured.
}

$pageTitle = 'Casino lobby';
$activePage = 'casino';
require ROOT_PATH . '/partials/header.php';
?>
<section class="page-shell lobby-hero">
    <div>
        <p class="eyebrow">Neon Originals</p>
        <h1>Find your next game.</h1>
        <p>Every result is generated on the server. Every credit stays virtual.</p>
    </div>
    <?php if ($jackpots !== []): ?>
        <a class="jackpot-banner" href="<?= e(url('/jackpots.php')) ?>">
            <span>Combined jackpots</span>
            <strong><?= e(format_credits(array_sum(array_column($jackpots, 'balance')))) ?></strong>
            <small>View all pots →</small>
        </a>
    <?php endif; ?>
</section>

<section class="page-shell">
    <div class="lobby-tools">
        <label class="search-control">
            <span class="visually-hidden">Search games</span>
            <span aria-hidden="true">⌕</span>
            <input type="search" placeholder="Search games" data-game-search>
        </label>
        <div class="chip-row" role="group" aria-label="Game categories">
            <?php
            $categories = [
                'all' => 'All games',
                'slots' => 'Slots',
                'table' => 'Table',
                'cards' => 'Cards',
                'quick' => 'Quick play',
            ];
            foreach ($categories as $value => $label):
            ?>
                <button class="filter-chip<?= $selectedCategory === $value ? ' is-active' : '' ?>"
                        type="button" data-game-filter="<?= e($value) ?>">
                    <?= e($label) ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="section-heading compact">
        <div>
            <p class="eyebrow">Play-money collection</p>
            <h2>All games</h2>
        </div>
        <span class="muted" data-game-count><?= count($games) ?> games</span>
    </div>

    <div class="game-grid" data-game-grid>
        <?php foreach ($games as $game): ?>
            <article class="game-card accent-<?= e($game['accent']) ?>"
                     data-game-card data-category="<?= e($game['category']) ?>"
                     data-title="<?= e(strtolower($game['title'])) ?>">
                <div class="game-art">
                    <span class="game-tag"><?= e($game['tag']) ?></span>
                    <span class="game-symbol" aria-hidden="true"><?= e($game['icon']) ?></span>
                    <span class="game-glow" aria-hidden="true"></span>
                </div>
                <div class="game-card-body">
                    <div>
                        <h3><?= e($game['title']) ?></h3>
                        <p><?= e($game['description']) ?></p>
                    </div>
                    <a class="play-button" href="<?= e(url('/games/' . $game['key'] . '.php')) ?>"
                       aria-label="Play <?= e($game['title']) ?> with virtual credits">
                        Play <span aria-hidden="true">→</span>
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <p class="empty-state" data-game-empty hidden>No games match that search.</p>
</section>

<section class="page-shell safe-play-callout">
    <strong>Keep play fun.</strong>
    <p>Set your own time limits, take regular breaks, and remember these credits cannot become money.</p>
    <a href="<?= e(url('/responsible-play.php')) ?>">Healthy play guide</a>
</section>
<?php require ROOT_PATH . '/partials/footer.php'; ?>

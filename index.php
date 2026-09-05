<?php
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$games = game_definitions();
$jackpots = jackpot_totals();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    verify_csrf();
    try {
        play_game((int) current_user()['id'], (string) $_POST['game'], (int) $_POST['bet'], ['selection' => $_POST['selection'] ?? null]);
        flash('Game settled successfully.', 'success');
    } catch (Throwable $e) {
        flash($e->getMessage(), 'danger');
    }
    header('Location: /index.php');
    exit;
}

render_header('Casino', 'casino');
?>
<section class="hero">
    <div class="panel">
        <span class="badge">Casino</span>
        <h1>Compact Neon Royale lobby</h1>
        <p class="muted">Original, Hostinger-ready play-money experience inspired by the supplied information architecture: casino tabs, live feeds, results, game cards and compact navigation.</p>
        <div class="toolbar">
            <div class="filters">
                <button type="button" class="active" data-filter="all">All</button>
                <button type="button" data-filter="slots">Slots</button>
                <button type="button" data-filter="table">Table</button>
                <button type="button" data-filter="cards">Cards</button>
                <button type="button" data-filter="instant">Instant</button>
            </div>
            <input type="search" placeholder="Search games" data-game-search>
        </div>
    </div>
    <div class="hero-promo">
        <div class="stat-card">
            <span class="badge">Live Sports</span>
            <h3 data-live-sports>Refreshing…</h3>
            <p class="muted">Virtual fixtures, odds buttons and server-side pick settlement.</p>
        </div>
        <div class="stat-card bonus-card">
            <span class="badge">Daily Bonus</span>
            <p>Claim a once-per-day credit boost from your profile or the API.</p>
            <a class="button-link" href="/profile.php">Open profile</a>
        </div>
    </div>
</section>
<section class="promo-grid">
    <?php foreach ($jackpots as $name => $value): ?>
        <article class="stat-card">
            <span class="badge">Jackpot</span>
            <h3><?= e($name) ?></h3>
            <strong><?= fmt_coins($value) ?></strong>
        </article>
    <?php endforeach; ?>
</section>
<section class="section-head">
    <h2>Casino categories</h2>
    <p class="muted">Five server-settled play-money games.</p>
</section>
<section class="game-grid">
    <?php foreach ($games as $key => $game): ?>
        <article class="game-card" data-category="<?= e($game['category']) ?>">
            <span class="badge"><?= e(ucfirst($game['category'])) ?></span>
            <h3><?= e($game['label']) ?></h3>
            <p class="muted"><?= e($game['description']) ?></p>
            <?php if ($user): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="game" value="<?= e($key) ?>">
                    <?php if (in_array($key, ['roulette', 'dice'], true)): ?>
                        <label>
                            <span><?= $key === 'roulette' ? 'Pick a color' : 'Pick a side' ?></span>
                            <select name="selection">
                                <?php if ($key === 'roulette'): ?>
                                    <option value="red">Red</option>
                                    <option value="black">Black</option>
                                <?php else: ?>
                                    <option value="high">High (4-6)</option>
                                    <option value="low">Low (1-3)</option>
                                <?php endif; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                    <label>
                        <span>Bet amount</span>
                        <input type="number" min="10" name="bet" value="100" required>
                    </label>
                    <button type="submit">Play now</button>
                </form>
            <?php else: ?>
                <p class="muted">Register or log in to place play-money bets.</p>
                <a class="button-link" href="/register.php">Create account</a>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
<?php render_footer('casino'); ?>

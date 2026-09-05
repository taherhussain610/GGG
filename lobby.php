<?php
$title = 'Casino';
$activeNav = 'casino';
require __DIR__ . '/partials/header.php';
$games = game_definitions();
$providerGames = provider_catalog();
$providerReady = provider_schema_available();
$user = current_user();
?>
<section class="section-head">
    <div>
        <span class="badge">Catalog</span>
        <h1>Casino and provider lobby</h1>
        <p class="muted">Local games plus a searchable fake provider feed. Every title uses demo credits only.</p>
    </div>
    <a class="button-link small" href="/provider/catalog.php">Catalog API</a>
</section>
<section class="panel">
    <div class="toolbar">
        <div class="filters">
            <button type="button" class="active" data-filter="all">All</button>
            <button type="button" data-filter="slots">Slots</button>
            <button type="button" data-filter="live">Live demo</button>
            <button type="button" data-filter="instant">Instant</button>
            <button type="button" data-filter="table">Table</button>
            <button type="button" data-filter="cards">Cards</button>
        </div>
        <input type="search" placeholder="Search title, category or provider" data-game-search>
    </div>
    <div class="filters provider-filters">
        <button type="button" class="active" data-provider-filter="all">All sources</button>
        <button type="button" data-provider-filter="native">Neon native</button>
        <button type="button" data-provider-filter="neon-demo">Demo provider</button>
    </div>
</section>
<section class="section-head">
    <div>
        <span class="badge">Native</span>
        <h2>Neon Royale games</h2>
    </div>
</section>
<section class="game-grid">
    <?php foreach ($games as $key => $game): ?>
        <article class="game-card" data-category="<?= e($game['category']) ?>" data-provider="native">
            <span class="badge"><?= e(ucfirst($game['category'])) ?></span>
            <h3><?= e($game['label']) ?></h3>
            <p class="muted"><?= e($game['description']) ?></p>
            <a class="button-link" href="/games/<?= e($key) ?>.php">Open game</a>
        </article>
    <?php endforeach; ?>
</section>
<section class="section-head">
    <div>
        <span class="badge">Provider feed</span>
        <h2>Sandbox catalog</h2>
        <p class="muted">Provider IDs, thumbnails and launch sessions are served by the local demo adapter.</p>
    </div>
</section>
<?php if (!$providerReady): ?>
    <div class="banner banner-warning">Previewing the fake feed. Import the provider tables from <code>/database.sql</code> to enable launches.</div>
<?php endif; ?>
<section class="game-grid">
    <?php foreach ($providerGames as $game): ?>
        <article class="game-card provider-game-card" data-category="<?= e($game['category']) ?>" data-provider="<?= e($game['provider_slug']) ?>">
            <img class="game-thumbnail" src="<?= e($game['thumbnail_url']) ?>" alt="">
            <div class="section-head">
                <span class="badge"><?= e(ucfirst($game['category'])) ?></span>
                <span class="health-dot health-<?= e($game['health_status']) ?>" title="<?= e($game['health_status']) ?>"></span>
            </div>
            <h3><?= e($game['title']) ?></h3>
            <p class="muted"><?= e($game['description']) ?></p>
            <dl class="detail-list compact">
                <div><dt>Provider</dt><dd><?= e($game['provider_name']) ?></dd></div>
                <div><dt>Game ID</dt><dd><code><?= e($game['provider_game_id']) ?></code></dd></div>
            </dl>
            <?php if ($user && (int) $game['id'] > 0): ?>
                <form method="post" action="/provider/launch.php">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="game_id" value="<?= (int) $game['id'] ?>">
                    <button type="submit">Launch demo</button>
                </form>
            <?php elseif (!$user): ?>
                <a class="button-link" href="/login.php">Log in to launch</a>
            <?php else: ?>
                <button type="button" class="secondary" disabled>Schema required</button>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>

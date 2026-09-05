<?php
require __DIR__ . '/includes/bootstrap.php';

$user = current_user();
$games = game_definitions();
$jackpots = jackpot_totals();
$dashboard = dashboard_snapshot();
$leaders = array_slice(leaderboard_rows(), 0, 5);
$results = recent_results();
$featuredEvents = array_slice(sports_events(), 0, 4);

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
<section class="stats-grid compact-stats">
    <article class="stat-card"><span class="badge">Players</span><h3 data-dashboard-players><?= number_format($dashboard['players']) ?></h3><p class="muted">Registered profiles</p></article>
    <article class="stat-card"><span class="badge">Top Wallet</span><h3 data-dashboard-top-balance><?= fmt_coins($dashboard['top_balance']) ?></h3><p class="muted">Best virtual bankroll</p></article>
    <article class="stat-card"><span class="badge">Open Picks</span><h3 data-dashboard-open-picks><?= number_format($dashboard['open_picks']) ?></h3><p class="muted">Pending sports slips</p></article>
    <article class="stat-card"><span class="badge">Jackpots</span><h3 data-dashboard-jackpot-total><?= fmt_coins($dashboard['jackpot_total']) ?></h3><p class="muted">Total virtual prize pools</p></article>
</section>
<section class="toolbar toolbar-tabs">
    <a class="mini-tab active" href="/index.php">Featured lobby</a>
    <a class="mini-tab" href="/lobby.php">Game routes</a>
    <a class="mini-tab" href="/sports.php">Sports board</a>
    <a class="mini-tab" href="/results.php">Results feed</a>
    <a class="mini-tab" href="/jackpots.php">Jackpots</a>
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
<section class="results-grid">
    <article class="result-card table-wrap">
        <div class="section-head">
            <div>
                <span class="badge">Live board</span>
                <h2>Featured virtual events</h2>
            </div>
            <a class="button-link small" href="/sports.php">Open sportsbook</a>
        </div>
        <table>
            <thead><tr><th>Event</th><th>Status</th><th>Markets</th></tr></thead>
            <tbody>
                <?php if (!$featuredEvents): ?>
                    <tr><td colspan="3" class="muted">No featured events available yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($featuredEvents as $event): ?>
                        <tr>
                            <td><?= e($event['sport']) ?> · <?= e($event['home_team']) ?> vs <?= e($event['away_team']) ?></td>
                            <td><?= e(ucfirst($event['status'])) ?></td>
                            <td><?= e((string) $event['home_odds']) ?> / <?= $event['draw_odds'] !== null ? e((string) $event['draw_odds']) . ' / ' : '' ?><?= e((string) $event['away_odds']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </article>
    <article class="result-card table-wrap">
        <div class="section-head">
            <div>
                <span class="badge">Leaders</span>
                <h2>Top players</h2>
            </div>
            <a class="button-link small" href="/leaderboard.php">Full table</a>
        </div>
        <table>
            <thead><tr><th>Player</th><th>Balance</th><th>Level</th></tr></thead>
            <tbody>
                <?php if (!$leaders): ?>
                    <tr><td colspan="3" class="muted">No leaderboard data yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($leaders as $leader): ?>
                        <tr>
                            <td><?= e($leader['username']) ?></td>
                            <td><?= fmt_coins((int) $leader['balance']) ?></td>
                            <td><?= (int) $leader['level'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </article>
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
<section class="results-grid">
    <article class="result-card table-wrap">
        <div class="section-head"><h2>Latest sports results</h2></div>
        <table>
            <thead><tr><th>Fixture</th><th>Result</th></tr></thead>
            <tbody>
                <?php $latestSports = array_slice($results['sports'], 0, 4); ?>
                <?php if (!$latestSports): ?>
                    <tr><td colspan="2" class="muted">No finished sports fixtures yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($latestSports as $row): ?>
                        <tr>
                            <td><?= e($row['sport']) ?> · <?= e($row['home_team']) ?> vs <?= e($row['away_team']) ?></td>
                            <td><?= e($row['result_summary'] ?: strtoupper((string) $row['winner'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </article>
    <article class="result-card table-wrap">
        <div class="section-head"><h2>Activity console</h2></div>
        <div class="console-box">
            <div class="console-line">API /api/balance.php · Wallet refresh</div>
            <div class="console-line">API /api/sports.php · Live summary</div>
            <div class="console-line">API /api/results.php · Result stream</div>
            <div class="console-line">API /api/dashboard.php · Widget snapshot</div>
        </div>
    </article>
</section>
<?php render_footer('casino'); ?>

<?php
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    verify_csrf();
    try {
        place_pick((int) current_user()['id'], (int) $_POST['event_id'], (string) $_POST['selection'], (int) $_POST['stake']);
        flash('Virtual pick placed.', 'success');
    } catch (Throwable $e) {
        flash($e->getMessage(), 'danger');
    }
    header('Location: /sports.php');
    exit;
}

$events = sports_events();
$summary = dashboard_snapshot();
render_header('Sports', 'sports');
?>
<section class="hero">
    <div class="panel">
        <span class="badge">Sports</span>
        <h1>Virtual cricket, football, tennis and basketball</h1>
        <p class="muted">Compact odds cards with live/upcoming states, market buttons and server-side settlement.</p>
    </div>
    <div class="hero-promo">
        <div class="stat-card">
            <span class="badge">Feed</span>
            <h3 data-live-sports>Refreshing…</h3>
            <p class="muted">Live and upcoming event counts update automatically.</p>
        </div>
        <div class="stat-card">
            <span class="badge">Results</span>
            <h3><a href="/results.php">Open results</a></h3>
            <p class="muted">Finished fixtures and casino logs stay in sync.</p>
        </div>
    </div>
</section>
<section class="stats-grid compact-stats">
    <article class="stat-card"><span class="badge live">Live</span><h3><?= number_format($summary['live_events']) ?></h3><p class="muted">Events in play</p></article>
    <article class="stat-card"><span class="badge upcoming">Upcoming</span><h3><?= number_format($summary['upcoming_events']) ?></h3><p class="muted">Ready for picks</p></article>
    <article class="stat-card"><span class="badge finished">Finished</span><h3><?= number_format($summary['finished_events']) ?></h3><p class="muted">Settled fixtures</p></article>
    <article class="stat-card"><span class="badge">Tickets</span><h3><?= number_format($summary['open_picks']) ?></h3><p class="muted">Open virtual slips</p></article>
</section>
<section class="toolbar toolbar-tabs">
    <button type="button" class="mini-tab active" data-panel-filter="all">All</button>
    <button type="button" class="mini-tab" data-panel-filter="live">Live</button>
    <button type="button" class="mini-tab" data-panel-filter="upcoming">Upcoming</button>
    <button type="button" class="mini-tab" data-panel-filter="finished">Finished</button>
</section>
<section class="panel table-wrap">
    <div class="section-head">
        <div>
            <span class="badge">Odds table</span>
            <h2>Quick market table</h2>
        </div>
        <a class="button-link small" href="/api/sports.php?details=1">JSON feed</a>
    </div>
    <table>
        <thead><tr><th>Sport</th><th>Fixture</th><th>Home</th><th>Draw</th><th>Away</th><th>Status</th></tr></thead>
        <tbody>
            <?php if (!$events): ?>
                <tr><td colspan="6" class="muted">No sports events loaded yet.</td></tr>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td><?= e($event['sport']) ?></td>
                        <td><?= e($event['home_team']) ?> vs <?= e($event['away_team']) ?></td>
                        <td><?= e((string) $event['home_odds']) ?></td>
                        <td><?= $event['draw_odds'] !== null ? e((string) $event['draw_odds']) : '—' ?></td>
                        <td><?= e((string) $event['away_odds']) ?></td>
                        <td><?= e(ucfirst($event['status'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</section>
<section class="market-grid">
    <?php foreach ($events as $event): ?>
        <article class="market-card" data-panel-category="<?= e($event['status']) ?>">
            <div class="section-head">
                <div>
                    <span class="badge <?= e($event['status']) ?>"><?= e(ucfirst($event['status'])) ?></span>
                    <h3><?= e($event['home_team']) ?> vs <?= e($event['away_team']) ?></h3>
                    <p class="muted"><?= e($event['sport']) ?> · <?= e($event['league']) ?> · <?= e($event['starts_at']) ?></p>
                </div>
            </div>
            <?php if ($event['status'] === 'finished'): ?>
                <p><?= e($event['result_summary'] ?: 'Settled') ?></p>
            <?php elseif (current_user()): ?>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                    <label>
                        <span>Selection</span>
                        <select name="selection">
                            <option value="home"><?= e($event['home_team']) ?> @ <?= e((string) $event['home_odds']) ?></option>
                            <?php if ($event['draw_odds'] !== null): ?>
                                <option value="draw">Draw @ <?= e((string) $event['draw_odds']) ?></option>
                            <?php endif; ?>
                            <option value="away"><?= e($event['away_team']) ?> @ <?= e((string) $event['away_odds']) ?></option>
                        </select>
                    </label>
                    <label>
                        <span>Stake</span>
                        <input type="number" name="stake" min="10" value="100" required>
                    </label>
                    <button type="submit">Place virtual pick</button>
                </form>
            <?php else: ?>
                <p class="muted">Log in to place a virtual sports pick.</p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
<?php render_footer('sports'); ?>

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
<section class="market-grid">
    <?php foreach ($events as $event): ?>
        <article class="market-card">
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

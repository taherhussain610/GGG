<?php
require __DIR__ . '/includes/bootstrap.php';
$results = recent_results();
$summary = dashboard_snapshot();
render_header('Results', 'results');
?>
<section class="hero">
    <div class="panel">
        <span class="badge">Results</span>
        <h1>Virtual sports results and casino feed</h1>
        <p class="muted">Results update from the same server-side data used by the sportsbook and casino forms.</p>
    </div>
    <div class="hero-promo">
        <div class="stat-card">
            <span class="badge">Live feed</span>
            <h3 data-live-results>Refreshing…</h3>
            <p class="muted">The JSON results API powers this count.</p>
        </div>
    </div>
</section>
<section class="stats-grid compact-stats">
    <article class="stat-card"><span class="badge">Sports</span><h3><?= number_format(count($results['sports'])) ?></h3><p class="muted">Recent sports results</p></article>
    <article class="stat-card"><span class="badge">Casino</span><h3><?= number_format(count($results['casino'])) ?></h3><p class="muted">Recent casino logs</p></article>
    <article class="stat-card"><span class="badge finished">Settled</span><h3><?= number_format($summary['finished_events']) ?></h3><p class="muted">Resolved fixtures</p></article>
    <article class="stat-card"><span class="badge">Rounds</span><h3><?= number_format($summary['casino_rounds']) ?></h3><p class="muted">Total casino rounds</p></article>
</section>
<section class="toolbar toolbar-tabs">
    <button type="button" class="mini-tab active" data-panel-filter="all">All</button>
    <button type="button" class="mini-tab" data-panel-filter="sports">Sports</button>
    <button type="button" class="mini-tab" data-panel-filter="casino">Casino</button>
</section>
<section class="results-grid">
    <article class="result-card table-wrap" data-panel-category="sports">
        <div class="section-head"><h2>Sports</h2></div>
        <table>
            <thead><tr><th>Match</th><th>Result</th><th>Kick-off</th></tr></thead>
            <tbody>
                <?php if (!$results['sports']): ?>
                    <tr><td colspan="3" class="muted">No sports results available yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($results['sports'] as $row): ?>
                        <tr>
                            <td><?= e($row['sport']) ?> · <?= e($row['home_team']) ?> vs <?= e($row['away_team']) ?></td>
                            <td><?= e($row['result_summary'] ?: strtoupper((string) $row['winner'])) ?></td>
                            <td><?= e($row['starts_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </article>
    <article class="result-card table-wrap" data-panel-category="casino">
        <div class="section-head"><h2>Casino</h2></div>
        <table>
            <thead><tr><th>Game</th><th>Bet</th><th>Payout</th><th>Result</th></tr></thead>
            <tbody>
                <?php if (!$results['casino']): ?>
                    <tr><td colspan="4" class="muted">No casino rounds recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($results['casino'] as $row): ?>
                        <tr>
                            <td><?= e(ucfirst($row['game'])) ?></td>
                            <td><?= fmt_coins((int) $row['bet']) ?></td>
                            <td><?= fmt_coins((int) $row['payout']) ?></td>
                            <td><?= e($row['result']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </article>
</section>
<?php render_footer('results'); ?>

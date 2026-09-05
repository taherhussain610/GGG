<?php
require __DIR__ . '/includes/bootstrap.php';
$results = recent_results();
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
<section class="results-grid">
    <article class="result-card table-wrap">
        <div class="section-head"><h2>Sports</h2></div>
        <table>
            <thead><tr><th>Match</th><th>Result</th><th>Kick-off</th></tr></thead>
            <tbody>
                <?php foreach ($results['sports'] as $row): ?>
                    <tr>
                        <td><?= e($row['sport']) ?> · <?= e($row['home_team']) ?> vs <?= e($row['away_team']) ?></td>
                        <td><?= e($row['result_summary'] ?: strtoupper((string) $row['winner'])) ?></td>
                        <td><?= e($row['starts_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </article>
    <article class="result-card table-wrap">
        <div class="section-head"><h2>Casino</h2></div>
        <table>
            <thead><tr><th>Game</th><th>Bet</th><th>Payout</th><th>Result</th></tr></thead>
            <tbody>
                <?php foreach ($results['casino'] as $row): ?>
                    <tr>
                        <td><?= e(ucfirst($row['game'])) ?></td>
                        <td><?= fmt_coins((int) $row['bet']) ?></td>
                        <td><?= fmt_coins((int) $row['payout']) ?></td>
                        <td><?= e($row['result']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </article>
</section>
<?php render_footer('results'); ?>

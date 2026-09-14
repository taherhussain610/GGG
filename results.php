<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$statement = db()->query(
    "SELECT id, sport, league, home_team, away_team, starts_at,
            result_selection, home_score, away_score
     FROM sports_events
     WHERE status = 'finished'
     ORDER BY starts_at DESC
     LIMIT 100"
);
$results = $statement->fetchAll();

$myPicks = [];
$viewer = current_user();
if ($viewer !== null) {
    $pickStatement = db()->prepare(
        'SELECT p.selection, p.odds, p.stake, p.potential_win, p.status, p.created_at,
                e.home_team, e.away_team
         FROM sports_picks p
         JOIN sports_events e ON e.id = p.event_id
         WHERE p.user_id = ?
         ORDER BY p.created_at DESC
         LIMIT 30'
    );
    $pickStatement->execute([(int) $viewer['id']]);
    $myPicks = $pickStatement->fetchAll();
}

$pageTitle = 'Results';
$activePage = 'results';
require ROOT_PATH . '/partials/header.php';
?>
<section class="page-shell page-intro split-intro">
    <div>
        <p class="eyebrow">Verified outcomes</p>
        <h1>Results</h1>
        <p>Completed virtual fixtures and the status of your play-money predictions.</p>
    </div>
    <span class="fair-badge">Updated in UTC</span>
</section>

<section class="page-shell tabbed-section" data-tabs>
    <div class="tab-list" role="tablist" aria-label="Result views">
        <button role="tab" aria-selected="true" aria-controls="all-results" id="all-results-tab">All results</button>
        <button role="tab" aria-selected="false" aria-controls="my-picks" id="my-picks-tab">My predictions</button>
    </div>

    <div id="all-results" role="tabpanel" aria-labelledby="all-results-tab">
        <div class="result-list">
            <?php if ($results === []): ?>
                <p class="empty-state">No fixtures have been settled yet.</p>
            <?php endif; ?>
            <?php foreach ($results as $result): ?>
                <article class="result-row">
                    <div class="result-meta">
                        <strong><?= e($result['sport']) ?></strong>
                        <span><?= e($result['league']) ?></span>
                        <time datetime="<?= e(gmdate(DATE_ATOM, strtotime((string) $result['starts_at']))) ?>">
                            <?= e(gmdate('M j, H:i', strtotime((string) $result['starts_at']))) ?> UTC
                        </time>
                    </div>
                    <div class="result-teams">
                        <span class="<?= $result['result_selection'] === 'home' ? 'winner' : '' ?>">
                            <?= e($result['home_team']) ?>
                        </span>
                        <strong>
                            <?= $result['home_score'] === null ? '—' : (int) $result['home_score'] ?>
                            :
                            <?= $result['away_score'] === null ? '—' : (int) $result['away_score'] ?>
                        </strong>
                        <span class="<?= $result['result_selection'] === 'away' ? 'winner' : '' ?>">
                            <?= e($result['away_team']) ?>
                        </span>
                    </div>
                    <span class="status-badge status-finished">
                        <?= $result['result_selection'] === 'draw' ? 'Draw' : ucfirst(e((string) $result['result_selection'])) . ' win' ?>
                    </span>
                </article>
            <?php endforeach; ?>
        </div>
    </div>

    <div id="my-picks" role="tabpanel" aria-labelledby="my-picks-tab" hidden>
        <?php if ($viewer === null): ?>
            <div class="empty-state">
                <p>Log in to see your prediction history.</p>
                <a class="button button-primary" href="<?= e(url('/login.php?return=/results.php')) ?>">Log in</a>
            </div>
        <?php elseif ($myPicks === []): ?>
            <div class="empty-state">
                <p>You have not made a prediction yet.</p>
                <a class="button button-secondary" href="<?= e(url('/sports.php')) ?>">Browse virtual sports</a>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr><th>Fixture</th><th>Pick</th><th>Stake</th><th>Return</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($myPicks as $pick): ?>
                        <tr>
                            <td><?= e($pick['home_team']) ?> vs <?= e($pick['away_team']) ?></td>
                            <td><?= e(ucfirst($pick['selection'])) ?> @ <?= e($pick['odds']) ?></td>
                            <td><?= e(format_credits($pick['stake'])) ?></td>
                            <td><?= e(format_credits($pick['potential_win'])) ?></td>
                            <td><span class="status-badge status-<?= e($pick['status']) ?>"><?= e(ucfirst($pick['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php require ROOT_PATH . '/partials/footer.php'; ?>

<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$statement = db()->query(
    'SELECT id, sport, league, home_team, away_team, starts_at,
            home_odds, draw_odds, away_odds, status
     FROM sports_events
     WHERE status IN ("upcoming", "live")
     ORDER BY FIELD(status, "live", "upcoming"), starts_at
     LIMIT 50'
);
$events = $statement->fetchAll();
$sports = array_values(array_unique(array_column($events, 'sport')));

$pageTitle = 'Virtual sports';
$activePage = 'sports';
require ROOT_PATH . '/partials/header.php';
?>
<section class="page-shell page-intro split-intro">
    <div>
        <p class="eyebrow">Simulated fixtures</p>
        <h1>Virtual sports</h1>
        <p>Make play-money predictions on generated events. Odds and results are for entertainment only.</p>
    </div>
    <div class="live-indicator" data-feed-status>
        <span class="status-dot" aria-hidden="true"></span>
        Feed connected
    </div>
</section>

<section class="page-shell">
    <div class="chip-row" role="group" aria-label="Filter sports">
        <button class="filter-chip is-active" type="button" data-sport-filter="all">All sports</button>
        <?php foreach ($sports as $sport): ?>
            <button class="filter-chip" type="button" data-sport-filter="<?= e(strtolower($sport)) ?>">
                <?= e($sport) ?>
            </button>
        <?php endforeach; ?>
    </div>

    <div class="sports-layout">
        <div class="event-list" data-sports-feed>
            <?php if ($events === []): ?>
                <div class="empty-state">No virtual events are open right now. Check results for completed fixtures.</div>
            <?php endif; ?>
            <?php foreach ($events as $event): ?>
                <article class="event-card" data-sport-card="<?= e(strtolower($event['sport'])) ?>">
                    <header>
                        <div>
                            <span class="sport-name"><?= e($event['sport']) ?></span>
                            <span><?= e($event['league']) ?></span>
                        </div>
                        <time datetime="<?= e(gmdate(DATE_ATOM, strtotime((string) $event['starts_at']))) ?>">
                            <?= e($event['status'] === 'live' ? 'Live now' : gmdate('D H:i', strtotime((string) $event['starts_at'])) . ' UTC') ?>
                        </time>
                    </header>
                    <div class="fixture">
                        <strong><?= e($event['home_team']) ?></strong>
                        <span>VS</span>
                        <strong><?= e($event['away_team']) ?></strong>
                    </div>
                    <form class="pick-form" data-pick-form>
                        <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
                        <fieldset>
                            <legend>Your prediction</legend>
                            <label>
                                <input type="radio" name="selection" value="home" required>
                                <span>Home <strong><?= e(number_format((float) $event['home_odds'], 2)) ?></strong></span>
                            </label>
                            <?php if ($event['draw_odds'] !== null): ?>
                                <label>
                                    <input type="radio" name="selection" value="draw" required>
                                    <span>Draw <strong><?= e(number_format((float) $event['draw_odds'], 2)) ?></strong></span>
                                </label>
                            <?php endif; ?>
                            <label>
                                <input type="radio" name="selection" value="away" required>
                                <span>Away <strong><?= e(number_format((float) $event['away_odds'], 2)) ?></strong></span>
                            </label>
                        </fieldset>
                        <div class="pick-submit">
                            <label>
                                <span>Stake</span>
                                <input type="number" name="stake" min="10" max="100000" step="10" value="100" required>
                            </label>
                            <?php if (current_user() === null): ?>
                                <a class="button button-primary" href="<?= e(url('/login.php?return=/sports.php')) ?>">Log in to predict</a>
                            <?php else: ?>
                                <button class="button button-primary" type="submit">Place prediction</button>
                            <?php endif; ?>
                        </div>
                        <p class="form-feedback" data-pick-feedback aria-live="polite"></p>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>

        <aside class="sports-sidebar">
            <div class="panel">
                <p class="eyebrow">How it works</p>
                <h2>Pick. Watch. Settle.</h2>
                <ol class="numbered-list">
                    <li>Choose an outcome before the event begins.</li>
                    <li>Set a stake using free virtual credits.</li>
                    <li>An admin settles the simulated final result.</li>
                </ol>
                <p class="muted small">Decimal multipliers include the returned stake.</p>
            </div>
            <a class="panel sidebar-link" href="<?= e(url('/results.php')) ?>">
                <span>Latest results</span><strong>View all →</strong>
            </a>
        </aside>
    </div>
</section>
<?php require ROOT_PATH . '/partials/footer.php'; ?>

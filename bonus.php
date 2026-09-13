<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$viewer = current_user();
$available = $viewer === null
    || $viewer['last_bonus_at'] === null
    || strtotime((string) $viewer['last_bonus_at']) <= time() - 86400;
$reward = $viewer === null ? 500 : 500 + min(500, max(0, ((int) $viewer['level'] - 1) * 25));
$nextClaim = !$available && $viewer !== null
    ? gmdate(DATE_ATOM, strtotime((string) $viewer['last_bonus_at']) + 86400)
    : null;

$pageTitle = 'Daily rewards';
$activePage = 'rewards';
require ROOT_PATH . '/partials/header.php';
?>
<section class="page-shell reward-hero">
    <div class="reward-orbit" aria-hidden="true">
        <span>✦</span>
    </div>
    <div>
        <p class="eyebrow">Daily drop</p>
        <h1>A free reward, every 24 hours.</h1>
        <p>Your level increases the daily amount, up to 1,000 virtual credits. No purchase is ever required.</p>
        <div class="reward-amount">
            <span>Today's reward</span>
            <strong><?= e(format_credits($reward)) ?></strong>
        </div>
        <?php if ($viewer === null): ?>
            <a class="button button-primary" href="<?= e(url('/login.php?return=/bonus.php')) ?>">Log in to claim</a>
        <?php else: ?>
            <button class="button button-primary" type="button" data-bonus-claim
                    <?= $available ? '' : 'disabled' ?>>
                <?= $available ? 'Claim daily credits' : 'Reward already claimed' ?>
            </button>
            <p class="form-feedback" data-bonus-feedback
               data-next-claim="<?= e($nextClaim) ?>" aria-live="polite">
                <?= $available ? 'Ready now.' : 'Your next reward unlocks after the timer.' ?>
            </p>
        <?php endif; ?>
    </div>
</section>

<section class="page-shell section-block">
    <div class="section-heading">
        <div><p class="eyebrow">More ways to progress</p><h2>Build your level</h2></div>
    </div>
    <div class="feature-grid">
        <a class="feature-card accent-purple" href="<?= e(url('/casino.php')) ?>">
            <span class="feature-icon">◇</span><div><strong>Play originals</strong><p>Every completed bet earns XP.</p></div><span>→</span>
        </a>
        <a class="feature-card accent-cyan" href="<?= e(url('/sports.php')) ?>">
            <span class="feature-icon">◎</span><div><strong>Make predictions</strong><p>Virtual sports stakes also earn XP.</p></div><span>→</span>
        </a>
        <a class="feature-card accent-gold" href="<?= e(url('/leaderboard.php')) ?>">
            <span class="feature-icon">♛</span><div><strong>Climb the ranks</strong><p>Compare XP without exposing balances.</p></div><span>→</span>
        </a>
    </div>
</section>
<?php require ROOT_PATH . '/partials/footer.php'; ?>

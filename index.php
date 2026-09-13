<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$pageTitle = 'Free play, electric nights';
$activePage = 'home';
require ROOT_PATH . '/partials/header.php';
?>
<section class="hero page-shell">
    <div class="hero-copy">
        <p class="eyebrow">Original play-money arcade</p>
        <h1>Big-game energy.<br><span>Zero-money stakes.</span></h1>
        <p class="hero-lead">
            Explore neon casino games and simulated sports predictions with free
            virtual credits. Nothing to buy. Nothing to cash out.
        </p>
        <div class="hero-actions">
            <a class="button button-primary" href="<?= e(url(current_user() ? '/casino.php' : '/register.php')) ?>">
                <?= current_user() ? 'Enter the lobby' : 'Create free account' ?>
            </a>
            <a class="button button-secondary" href="<?= e(url('/sports.php')) ?>">Browse sports</a>
        </div>
        <ul class="trust-list" aria-label="Platform highlights">
            <li>10,000 starter credits</li>
            <li>Server-generated outcomes</li>
            <li>No payment methods</li>
        </ul>
    </div>
    <div class="hero-visual" aria-hidden="true">
        <div class="orb orb-one"></div>
        <div class="orb orb-two"></div>
        <div class="hero-card hero-card-back">
            <span>DAILY DROP</span>
            <strong>+500 CR</strong>
        </div>
        <div class="hero-card hero-card-main">
            <span class="mini-label">FEATURED TABLE</span>
            <div class="roulette-art">17</div>
            <strong>Neon Roulette</strong>
            <small>Single-zero wheel</small>
        </div>
    </div>
</section>

<section class="page-shell section-block" aria-labelledby="choose-heading">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Choose your arena</p>
            <h2 id="choose-heading">One balance. Three ways to play.</h2>
        </div>
    </div>
    <div class="feature-grid">
        <a class="feature-card accent-purple" href="<?= e(url('/casino.php')) ?>">
            <span class="feature-icon" aria-hidden="true">♠</span>
            <div><strong>Casino lobby</strong><p>Five original games, from fast dice to strategic cards.</p></div>
            <span aria-hidden="true">→</span>
        </a>
        <a class="feature-card accent-cyan" href="<?= e(url('/sports.php')) ?>">
            <span class="feature-icon" aria-hidden="true">◎</span>
            <div><strong>Virtual sports</strong><p>Make predictions on simulated fixtures and leagues.</p></div>
            <span aria-hidden="true">→</span>
        </a>
        <a class="feature-card accent-gold" href="<?= e(url('/bonus.php')) ?>">
            <span class="feature-icon" aria-hidden="true">✦</span>
            <div><strong>Daily rewards</strong><p>Return each day, build XP, and climb the leaderboard.</p></div>
            <span aria-hidden="true">→</span>
        </a>
    </div>
</section>

<section class="page-shell explainer section-block" aria-labelledby="fair-heading">
    <div>
        <p class="eyebrow">Designed for transparent play</p>
        <h2 id="fair-heading">The server decides every result.</h2>
    </div>
    <p>
        Bets, cards, dice, wheel results, payouts, and balance changes are
        calculated on the server with PHP's cryptographically secure random
        generator. Browser animations only reveal the saved result.
    </p>
</section>
<?php require ROOT_PATH . '/partials/footer.php'; ?>

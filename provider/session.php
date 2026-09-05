<?php
require __DIR__ . '/../includes/bootstrap.php';
require_login();

$user = current_user();
$token = strtolower(trim((string) ($_GET['token'] ?? '')));
$session = provider_session_by_token($token, (int) $user['id']);

if (!$session) {
    http_response_code(404);
    render_header('Session unavailable', 'casino');
    ?>
    <section class="panel auth-card">
        <span class="badge">Provider session</span>
        <h1>Session unavailable</h1>
        <p class="muted">This launch token is invalid or does not belong to your account.</p>
        <a class="button-link" href="/lobby.php">Return to catalog</a>
    </section>
    <?php
    render_footer('casino');
    exit;
}

$canPlay = $session['status'] === 'active'
    && (int) $session['provider_enabled'] === 1
    && (int) $session['game_enabled'] === 1
    && $session['adapter'] === 'demo';
render_header($session['title'], 'casino');
?>
<section class="provider-session">
    <article class="panel provider-stage">
        <img class="provider-hero-image" src="<?= e($session['thumbnail_url']) ?>" alt="">
        <span class="badge"><?= e(ucfirst($session['category'])) ?> · sandbox</span>
        <h1><?= e($session['title']) ?></h1>
        <p><?= e($session['description']) ?></p>
        <p class="muted">
            <?= e($session['provider_name']) ?> · session <?= e($session['public_id']) ?>
            · expires <?= e($session['expires_at']) ?>
        </p>
        <div class="sandbox-notice">Demo credits only. No deposits, withdrawals, cash-out, external game stream, or real-money provider call is available.</div>
    </article>
    <aside class="panel">
        <span class="badge <?= $canPlay ? 'live' : 'finished' ?>"><?= e(ucfirst($session['status'])) ?></span>
        <h2>Demo wallet</h2>
        <p class="provider-balance"><?= fmt_coins((int) $user['balance']) ?></p>
        <?php if ($canPlay): ?>
            <form method="post" action="/provider/wallet.php" class="provider-play-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input type="hidden" name="action" value="play">
                <input type="hidden" name="request_id" value="<?= e(bin2hex(random_bytes(16))) ?>">
                <label>
                    <span>Virtual-credit bet</span>
                    <input type="number" name="bet" min="<?= (int) $session['min_bet'] ?>" max="<?= (int) $session['max_bet'] ?>" value="<?= (int) $session['min_bet'] * 10 ?>" required>
                </label>
                <button type="submit">Play demo round</button>
            </form>
            <form method="post" action="/provider/wallet.php" class="provider-close-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input type="hidden" name="action" value="close">
                <button type="submit" class="secondary">Close session</button>
            </form>
        <?php else: ?>
            <p class="muted">This session cannot accept more rounds. Return to the catalog to launch a new one.</p>
            <a class="button-link" href="/lobby.php">Return to catalog</a>
        <?php endif; ?>
    </aside>
</section>
<?php render_footer('casino'); ?>

<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $bonus = claim_bonus((int) current_user()['id']);
        flash('You received ' . fmt_coins($bonus['amount']) . ' in virtual coins.', 'success');
    } catch (Throwable $e) {
        flash($e->getMessage(), 'danger');
    }
    header('Location: /daily_bonus.php');
    exit;
}

$user = current_user();
$title = 'Daily Bonus';
$activeNav = 'profile';
require __DIR__ . '/partials/header.php';
$nextReady = $user['last_bonus_at'] ? strtotime((string) $user['last_bonus_at']) + 86400 : null;
$available = $nextReady === null || $nextReady <= time();
$rewardHint = 500 + ((int) $user['level'] * 100);
?>
<section class="panel bonus-card form">
    <span class="badge">Daily Bonus</span>
    <h1>Daily virtual reward</h1>
    <p class="muted">Claim a level-scaled play-money boost once every 24 hours.</p>
    <p><?= $available ? 'Your bonus is ready now.' : 'Your next bonus unlocks after the 24-hour cooldown.' ?></p>
    <p class="muted">Estimated reward: <?= fmt_coins($rewardHint) ?></p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button type="submit" <?= $available ? '' : 'disabled' ?>>Claim daily bonus</button>
    </form>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>

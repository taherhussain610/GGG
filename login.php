<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_guest();

$error = '';
$email = '';
$returnPath = safe_return_path(isset($_GET['return']) ? (string) $_GET['return'] : null);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        verify_csrf();
        rate_limit('login-ip', 30, 900);
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $returnPath = safe_return_path(isset($_POST['return']) ? (string) $_POST['return'] : null);

        $statement = db()->prepare(
            'SELECT id, password_hash, is_suspended FROM users WHERE email = ? LIMIT 1'
        );
        $statement->execute([$email]);
        $record = $statement->fetch();
        if ($record !== false) {
            rate_limit('login-account:' . (int) $record['id'], 8, 900, false);
        }

        if (
            $record === false
            || (int) $record['is_suspended'] === 1
            || !password_verify($password, $record['password_hash'])
        ) {
            throw new DomainException('Email or password is incorrect.');
        }

        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        if (password_needs_rehash($record['password_hash'], $algorithm)) {
            $update = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $update->execute([password_hash($password, $algorithm), (int) $record['id']]);
        }

        login_user((int) $record['id']);
        redirect($returnPath);
    } catch (DomainException $exception) {
        if ($exception instanceof HttpException) {
            http_response_code($exception->status);
        }
        $error = $exception->getMessage();
    }
}

$pageTitle = 'Log in';
require ROOT_PATH . '/partials/header.php';
?>
<section class="auth-shell auth-shell-compact">
    <div class="auth-promo">
        <p class="eyebrow">Welcome back</p>
        <h1>Your next round is ready.</h1>
        <p>Pick up your daily reward, check your level, or explore today's simulated fixtures.</p>
    </div>
    <div class="panel auth-card">
        <h2>Log in</h2>
        <p class="muted">New here? <a href="<?= e(url('/register.php')) ?>">Create a free account</a>.</p>
        <?php if ($error !== ''): ?>
            <div class="form-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" class="stacked-form">
            <?= csrf_input() ?>
            <input type="hidden" name="return" value="<?= e($returnPath) ?>">
            <label>
                Email
                <input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required>
            </label>
            <label>
                Password
                <input type="password" name="password" autocomplete="current-password" required>
            </label>
            <button class="button button-primary button-wide" type="submit">Log in</button>
        </form>
    </div>
</section>
<?php require ROOT_PATH . '/partials/footer.php'; ?>

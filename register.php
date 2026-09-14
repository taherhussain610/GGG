<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require_guest();

$error = '';
$values = ['username' => '', 'email' => ''];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        verify_csrf();
        rate_limit('register', 5, 900);

        $values['username'] = trim((string) ($_POST['username'] ?? ''));
        $values['email'] = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if (preg_match('/^[A-Za-z0-9_]{3,24}$/', $values['username']) !== 1) {
            throw new DomainException('Username must be 3–24 letters, numbers, or underscores.');
        }
        if (filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false || strlen($values['email']) > 190) {
            throw new DomainException('Enter a valid email address.');
        }
        if (strlen($password) < 10 || strlen($password) > 255) {
            throw new DomainException('Password must be between 10 and 255 characters.');
        }
        if (!isset($_POST['play_money_acknowledgement'])) {
            throw new DomainException('Confirm that you understand credits have no monetary value.');
        }

        $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $statement = $pdo->prepare(
                'INSERT INTO users(username, email, password_hash) VALUES(?, ?, ?)'
            );
            $statement->execute([
                $values['username'],
                $values['email'],
                password_hash($password, $algorithm),
            ]);
            $userId = (int) $pdo->lastInsertId();
            add_wallet_entry($pdo, $userId, 10000, 'welcome_bonus');
            $pdo->commit();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($exception->getCode() === '23000') {
                throw new DomainException('That username or email is already registered.');
            }
            throw $exception;
        }

        login_user($userId);
        flash('success', 'Welcome to Neon Royale. Your 10,000 free credits are ready.');
        redirect('/casino.php');
    } catch (DomainException $exception) {
        if ($exception instanceof HttpException) {
            http_response_code($exception->status);
        }
        $error = $exception->getMessage();
    }
}

$pageTitle = 'Create account';
require ROOT_PATH . '/partials/header.php';
?>
<section class="auth-shell">
    <div class="auth-promo">
        <p class="eyebrow">Your free pass</p>
        <h1>Start with 10,000 credits.</h1>
        <p>Play original casino games, predict simulated sports, unlock levels, and return for daily rewards.</p>
        <ul class="check-list">
            <li>No card or payment details</li>
            <li>No deposits or withdrawals</li>
            <li>Credits have no real-world value</li>
        </ul>
    </div>
    <div class="panel auth-card">
        <h2>Create your account</h2>
        <p class="muted">Already registered? <a href="<?= e(url('/login.php')) ?>">Log in</a>.</p>
        <?php if ($error !== ''): ?>
            <div class="form-error" role="alert"><?= e($error) ?></div>
        <?php endif; ?>
        <form method="post" class="stacked-form">
            <?= csrf_input() ?>
            <label>
                Username
                <input name="username" value="<?= e($values['username']) ?>" autocomplete="username"
                       required minlength="3" maxlength="24" pattern="[A-Za-z0-9_]+">
            </label>
            <label>
                Email
                <input type="email" name="email" value="<?= e($values['email']) ?>"
                       autocomplete="email" required maxlength="190">
            </label>
            <label>
                Password
                <input type="password" name="password" autocomplete="new-password"
                       required minlength="10" maxlength="255">
                <small>Use at least 10 characters.</small>
            </label>
            <label class="check-control">
                <input type="checkbox" name="play_money_acknowledgement" value="1" required>
                <span>I understand credits are free, have no monetary value, and cannot be withdrawn.</span>
            </label>
            <button class="button button-primary button-wide" type="submit">Create free account</button>
        </form>
    </div>
</section>
<?php require ROOT_PATH . '/partials/footer.php'; ?>

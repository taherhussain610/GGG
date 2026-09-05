<?php
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = $_POST['email'] ?? null;
    $password = $_POST['password'] ?? null;
    if (!is_string($email) || !is_string($password)) {
        flash('Email and password are required.', 'danger');
    } elseif (attempt_login($email, $password)) {
        flash('Welcome back.', 'success');
        header('Location: /profile.php');
        exit;
    } else {
        flash('Invalid email or password.', 'danger');
    }
}

render_header('Login');
?>
<section class="auth-card panel">
    <span class="badge">Login</span>
    <h1>Return to the lobby</h1>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label><span>Email</span><input type="email" name="email" required></label>
        <label><span>Password</span><input type="password" name="password" required></label>
        <button type="submit">Login</button>
    </form>
</section>
<?php render_footer(); ?>

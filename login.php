<?php
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (attempt_login((string) $_POST['email'], (string) $_POST['password'])) {
        flash('Welcome back.', 'success');
        header('Location: /profile.php');
        exit;
    }
    flash('Invalid email or password.', 'danger');
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

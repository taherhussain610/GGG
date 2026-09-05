<?php
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        register_user((string) $_POST['username'], (string) $_POST['email'], (string) $_POST['password']);
        flash('Welcome to Neon Royale.', 'success');
        header('Location: /profile.php');
        exit;
    } catch (Throwable $e) {
        flash($e->getMessage(), 'danger');
    }
}

render_header('Register');
?>
<section class="auth-card panel">
    <span class="badge">Register</span>
    <h1>Create your play-money account</h1>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <label><span>Username</span><input type="text" name="username" required maxlength="40"></label>
        <label><span>Email</span><input type="email" name="email" required maxlength="190"></label>
        <label><span>Password</span><input type="password" name="password" required minlength="8"></label>
        <button type="submit">Register</button>
    </form>
</section>
<?php render_footer(); ?>

<?php
require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = $_POST['username'] ?? null;
    $email = $_POST['email'] ?? null;
    $password = $_POST['password'] ?? null;

    if (!is_string($username) || !is_string($email) || !is_string($password)) {
        flash('Username, email and password are required.', 'danger');
    } else {
        try {
            register_user($username, $email, $password);
            flash('Welcome to Neon Royale.', 'success');
            header('Location: /profile.php');
            exit;
        } catch (Throwable $e) {
            flash($e->getMessage(), 'danger');
        }
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

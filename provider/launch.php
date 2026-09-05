<?php
require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}
if (!current_user()) {
    if (provider_wants_json()) {
        json_response(['ok' => false, 'error' => 'Authentication required'], 401);
    }
    header('Location: /login.php');
    exit;
}

verify_csrf();
$gameId = (int) ($_POST['game_id'] ?? 0);

try {
    $session = provider_create_session((int) current_user()['id'], $gameId);
    $launchUrl = '/provider/session.php?token=' . rawurlencode($session['token']);
    if (provider_wants_json()) {
        json_response([
            'ok' => true,
            'session_id' => $session['public_id'],
            'launch_url' => $launchUrl,
            'expires_in' => 1800,
        ], 201);
    }

    header('Location: ' . $launchUrl);
    exit;
} catch (InvalidArgumentException $e) {
    if (provider_wants_json()) {
        json_response(['ok' => false, 'error' => $e->getMessage()], 400);
    }
    flash($e->getMessage(), 'danger');
} catch (Throwable) {
    if (provider_wants_json()) {
        json_response(['ok' => false, 'error' => 'Unable to launch the provider game'], 500);
    }
    flash('Unable to launch the provider game.', 'danger');
}

header('Location: /lobby.php');
exit;

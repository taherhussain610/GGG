<?php
require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}
if (!current_user()) {
    json_response(['ok' => false, 'error' => 'Authentication required'], 401);
}

verify_csrf();
$userId = (int) current_user()['id'];
$token = strtolower(trim((string) ($_POST['token'] ?? '')));
$action = trim((string) ($_POST['action'] ?? 'play'));

try {
    if ($action === 'close') {
        provider_close_session($userId, $token);
        if (provider_wants_json()) {
            json_response(['ok' => true, 'status' => 'closed']);
        }
        flash('Provider session closed.', 'success');
        header('Location: /lobby.php');
        exit;
    }
    if ($action !== 'play') {
        throw new InvalidArgumentException('Unsupported wallet action.');
    }

    $result = provider_play_round(
        $userId,
        $token,
        (int) ($_POST['bet'] ?? 0),
        trim((string) ($_POST['request_id'] ?? ''))
    );
    if (provider_wants_json()) {
        json_response(['ok' => true, 'round' => $result]);
    }
    flash(
        $result['result'] . '. Bet ' . fmt_coins((int) $result['bet'])
        . ', payout ' . fmt_coins((int) $result['payout']) . '.',
        (int) $result['payout'] > 0 ? 'success' : 'info'
    );
} catch (InvalidArgumentException $e) {
    if (provider_wants_json()) {
        json_response(['ok' => false, 'error' => $e->getMessage()], 400);
    }
    flash($e->getMessage(), 'danger');
} catch (Throwable) {
    if (provider_wants_json()) {
        json_response(['ok' => false, 'error' => 'Unable to settle the demo round'], 500);
    }
    flash('Unable to settle the demo round.', 'danger');
}

$redirect = preg_match('/^[a-f0-9]{64}$/', $token)
    ? '/provider/session.php?token=' . rawurlencode($token)
    : '/lobby.php';
header('Location: ' . $redirect);
exit;

<?php
require __DIR__ . '/../includes/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}
if (!current_user()) {
    json_response(['ok' => false, 'error' => 'Authentication required'], 401);
}
verify_csrf();
$eventId = (int) ($_POST['event_id'] ?? 0);
$selection = is_string($_POST['selection'] ?? null) ? $_POST['selection'] : '';
$stake = (int) ($_POST['stake'] ?? 100);
if ($stake < 10) {
    json_response(['ok' => false, 'error' => 'Invalid stake'], 400);
}
try {
    $eventStmt = db()->prepare('SELECT * FROM sports_events WHERE id = ?');
    $eventStmt->execute([$eventId]);
    $event = $eventStmt->fetch();
    if (!$event) {
        json_response(['ok' => false, 'error' => 'Event unavailable'], 404);
    }
    $oddsMap = [
        'home' => (float) $event['home_odds'],
        'draw' => $event['draw_odds'] !== null ? (float) $event['draw_odds'] : 0.0,
        'away' => (float) $event['away_odds'],
    ];
    $odds = $oddsMap[$selection] ?? 0.0;
    if ($odds <= 0) {
        json_response(['ok' => false, 'error' => 'Selection unavailable'], 400);
    }
    place_pick((int) current_user()['id'], $eventId, $selection, $stake);
    json_response(['ok' => true, 'potential_win' => (int) round($stake * $odds)]);
} catch (InvalidArgumentException $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 400);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => 'Unable to place pick'], 500);
}

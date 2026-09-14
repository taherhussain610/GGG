<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$statement = db()->query(
    "SELECT id, sport, league, home_team, away_team, starts_at,
            home_odds, draw_odds, away_odds, status
     FROM sports_events
     WHERE status IN ('upcoming', 'live')
     ORDER BY FIELD(status, 'live', 'upcoming'), starts_at
     LIMIT 50"
);
$events = array_map(static function (array $event): array {
    return [
        'id' => (int) $event['id'],
        'sport' => $event['sport'],
        'league' => $event['league'],
        'home_team' => $event['home_team'],
        'away_team' => $event['away_team'],
        'starts_at' => gmdate(DATE_ATOM, strtotime((string) $event['starts_at'])),
        'home_odds' => (float) $event['home_odds'],
        'draw_odds' => $event['draw_odds'] === null ? null : (float) $event['draw_odds'],
        'away_odds' => (float) $event['away_odds'],
        'status' => $event['status'],
    ];
}, $statement->fetchAll());

json_response([
    'ok' => true,
    'generated_at' => gmdate(DATE_ATOM),
    'events' => $events,
]);

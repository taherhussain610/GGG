<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$statement = db()->query(
    "SELECT id, sport, league, home_team, away_team, starts_at,
            result_selection, home_score, away_score
     FROM sports_events
     WHERE status = 'finished'
     ORDER BY starts_at DESC
     LIMIT 100"
);

json_response([
    'ok' => true,
    'results' => array_map(static fn (array $result): array => [
        'id' => (int) $result['id'],
        'sport' => $result['sport'],
        'league' => $result['league'],
        'home_team' => $result['home_team'],
        'away_team' => $result['away_team'],
        'starts_at' => gmdate(DATE_ATOM, strtotime((string) $result['starts_at'])),
        'winner' => $result['result_selection'],
        'home_score' => $result['home_score'] === null ? null : (int) $result['home_score'],
        'away_score' => $result['away_score'] === null ? null : (int) $result['away_score'],
    ], $statement->fetchAll()),
]);

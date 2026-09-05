<?php
require __DIR__ . '/../includes/bootstrap.php';

$snapshot = dashboard_snapshot();
$leaders = array_slice(leaderboard_rows(), 0, 5);
$results = recent_results();

json_response([
    'ok' => true,
    'snapshot' => $snapshot,
    'leaders' => $leaders,
    'recent_sports' => array_slice($results['sports'], 0, 4),
    'recent_casino' => array_slice($results['casino'], 0, 4),
]);

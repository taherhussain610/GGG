<?php
require __DIR__ . '/../includes/bootstrap.php';
$events = sports_events();
$live = 0;
$upcoming = 0;
foreach ($events as $event) {
    if ($event['status'] === 'live') {
        $live++;
    }
    if ($event['status'] === 'upcoming') {
        $upcoming++;
    }
}
json_response([
    'ok' => true,
    'live_count' => $live,
    'upcoming_count' => $upcoming,
    ] + (isset($_GET['details']) ? ['events' => $events, 'rows' => $events] : []));

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
    'live_count' => $live,
    'upcoming_count' => $upcoming,
    'events' => $events,
]);

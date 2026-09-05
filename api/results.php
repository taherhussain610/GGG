<?php
require __DIR__ . '/../includes/bootstrap.php';
$results = recent_results();
json_response($results + ['ok' => true, 'results' => $results['sports']]);

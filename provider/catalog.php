<?php
require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$category = trim((string) ($_GET['category'] ?? ''));
if ($category !== '' && !in_array($category, ['slots', 'live', 'instant', 'table', 'cards'], true)) {
    json_response(['ok' => false, 'error' => 'Invalid category'], 400);
}

$filters = [
    'search' => (string) ($_GET['search'] ?? ''),
    'category' => $category,
    'provider' => (string) ($_GET['provider'] ?? ''),
];
$games = provider_catalog($filters);

json_response([
    'ok' => true,
    'mode' => provider_mode(),
    'currency' => 'CR',
    'count' => count($games),
    'games' => $games,
]);

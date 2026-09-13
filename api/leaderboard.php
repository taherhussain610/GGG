<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
}

$statement = db()->query(
    'SELECT username, xp, level
     FROM users
     WHERE is_suspended = 0
     ORDER BY xp DESC, created_at ASC
     LIMIT 50'
);
$leaders = $statement->fetchAll();

json_response([
    'ok' => true,
    'leaders' => array_map(static fn (array $leader, int $index): array => [
        'rank' => $index + 1,
        'username' => $leader['username'],
        'xp' => (int) $leader['xp'],
        'level' => (int) $leader['level'],
    ], $leaders, array_keys($leaders)),
]);

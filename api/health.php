<?php
require __DIR__ . '/../includes/bootstrap.php';
try {
    $status = db_available();
    json_response([
        'ok' => $status,
        'database' => $status ? 'connected' : 'unavailable',
    ], $status ? 200 : 503);
} catch (Throwable $e) {
    json_response(['ok' => false, 'database' => 'unavailable'], 503);
}

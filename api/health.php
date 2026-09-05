<?php
require __DIR__ . '/../includes/bootstrap.php';
try {
    $status = db_available();
    json_response([
        'ok' => $status,
        'app' => app_config()['app_name'],
        'database' => $status ? 'connected' : 'unavailable',
        'php' => PHP_VERSION,
    ], $status ? 200 : 503);
} catch (Throwable $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], 503);
}

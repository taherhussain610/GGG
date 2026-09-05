<?php
require __DIR__ . '/../includes/bootstrap.php';
try {
    $status = db_available();
    $providers = provider_health_overview();
    json_response([
        'ok' => $status,
        'database' => $status ? 'connected' : 'unavailable',
        'provider_layer' => $providers,
    ], $status ? 200 : 503);
} catch (Throwable $e) {
    json_response(['ok' => false, 'database' => 'unavailable'], 503);
}

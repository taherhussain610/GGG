<?php
require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 65536) {
    json_response(['ok' => false, 'error' => 'Payload too large'], 413);
}

$stream = fopen('php://input', 'rb');
$body = is_resource($stream) ? stream_get_contents($stream, 65537) : false;
if (is_resource($stream)) {
    fclose($stream);
}
if (!is_string($body)) {
    json_response(['ok' => false, 'error' => 'Unable to read webhook body'], 400);
}
if (strlen($body) > 65536) {
    json_response(['ok' => false, 'error' => 'Payload too large'], 413);
}

try {
    $result = provider_handle_webhook(
        (string) ($_SERVER['SCRIPT_NAME'] ?? '/provider/webhook.php'),
        $body,
        [
            'provider_id' => $_SERVER['HTTP_X_PROVIDER_ID'] ?? '',
            'timestamp' => $_SERVER['HTTP_X_PROVIDER_TIMESTAMP'] ?? '',
            'signature' => $_SERVER['HTTP_X_PROVIDER_SIGNATURE'] ?? '',
        ]
    );
    json_response(['ok' => true] + $result, $result['duplicate'] ? 200 : 202);
} catch (ProviderRequestException $e) {
    json_response(['ok' => false, 'error' => $e->getMessage()], $e->statusCode);
} catch (Throwable) {
    json_response(['ok' => false, 'error' => 'Unable to process provider webhook'], 500);
}

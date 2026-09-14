<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

require ROOT_PATH . '/config/config.php';
require ROOT_PATH . '/includes/functions.php';

if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
}
ini_set('log_errors', '1');

if (PHP_SAPI !== 'cli') {
    set_exception_handler(static function (Throwable $exception): never {
        error_log(sprintf(
            '[Neon Royale] %s in %s:%d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ));

        if (is_api_request()) {
            $status = $exception instanceof HttpException
                ? $exception->status
                : ($exception instanceof DomainException ? 422 : 500);
            $message = $exception instanceof DomainException
                ? $exception->getMessage()
                : 'The request could not be completed.';
            json_response(['ok' => false, 'error' => $message], $status);
        }

        http_response_code(500);
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>Service unavailable</title>';
        echo '<body><h1>Service temporarily unavailable</h1><p>Please try again later.</p></body></html>';
        exit;
    });
}

if (APP_ENV === 'production' && (strlen(APP_KEY) < 32 || str_starts_with(APP_KEY, 'change-this'))) {
    throw new RuntimeException('APP_KEY must be configured before production use.');
}

$isHttps = ($_SERVER['HTTPS'] ?? '') === 'on'
    || (TRUST_PROXY_HEADERS && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (PHP_SAPI !== 'cli' && APP_ENV === 'production' && !$isHttps) {
    if (!str_starts_with(APP_URL, 'https://')) {
        throw new RuntimeException('APP_URL must be an HTTPS URL in production.');
    }
    $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $requestUri = str_replace(["\r", "\n"], '', $requestUri);
    header('Location: ' . APP_URL . '/' . ltrim($requestUri, '/'), true, 308);
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('neon_royale_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (PHP_SAPI !== 'cli' && !headers_sent()) {
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; img-src 'self' data:; object-src 'none'; script-src 'self'; style-src 'self'");
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('Permissions-Policy: camera=(), geolocation=(), microphone=(), payment=()');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');

    if ($isHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

}

<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));

require ROOT_PATH . '/config/config.php';
require ROOT_PATH . '/includes/functions.php';

if (APP_ENV === 'production' && (strlen(APP_KEY) < 32 || str_starts_with(APP_KEY, 'change-this'))) {
    throw new RuntimeException('APP_KEY must be configured before production use.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = ($_SERVER['HTTPS'] ?? '') === 'on'
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

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

    if (($_SERVER['HTTPS'] ?? '') === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    set_exception_handler(static function (Throwable $exception): never {
        error_log(sprintf(
            '[Neon Royale] %s in %s:%d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        ));

        if (is_api_request()) {
            $status = $exception instanceof DomainException ? 422 : 500;
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

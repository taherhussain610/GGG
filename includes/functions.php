<?php

declare(strict_types=1);

final class HttpException extends DomainException
{
    public function __construct(string $message, public readonly int $status)
    {
        parent::__construct($message);
    }
}

function db(): PDO
{
    static $connection = null;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );
    $connection = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
    $connection->exec("SET time_zone = '+00:00'");

    return $connection;
}

function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    if ($path === '') {
        return APP_URL === '' ? '/' : APP_URL . '/';
    }

    return APP_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path), true, 303);
    exit;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $provided = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
    if (!is_string($provided) || !hash_equals(csrf_token(), $provided)) {
        throw new HttpException('Your session token expired. Refresh the page and try again.', 403);
    }
}

function current_user(bool $refresh = false): ?array
{
    static $loaded = false;
    static $user = null;

    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    if (!$loaded || $refresh) {
        $statement = db()->prepare(
            'SELECT id, username, email, balance, xp, level, is_admin, is_suspended,
                    last_bonus_at, created_at
             FROM users WHERE id = ? LIMIT 1'
        );
        $statement->execute([(int) $_SESSION['user_id']]);
        $record = $statement->fetch();
        $user = $record === false ? null : $record;
        $loaded = true;

        if ($user === null || (int) $user['is_suspended'] === 1) {
            unset($_SESSION['user_id']);
            $user = null;
        }
    }

    return $user;
}

function login_user(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    unset($_SESSION['csrf_token']);
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function require_auth(): array
{
    $user = current_user();
    if ($user === null) {
        if (is_api_request()) {
            json_response(['ok' => false, 'error' => 'Authentication required.'], 401);
        }
        flash('error', 'Log in to continue.');
        redirect('/login.php');
    }

    return $user;
}

function require_guest(): void
{
    if (current_user() !== null) {
        redirect('/casino.php');
    }
}

function require_admin(): array
{
    $user = require_auth();
    if ((int) $user['is_admin'] !== 1) {
        if (is_api_request()) {
            json_response(['ok' => false, 'error' => 'Administrator access required.'], 403);
        }
        http_response_code(403);
        exit('Administrator access required.');
    }

    return $user;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);

    return is_array($messages) ? $messages : [];
}

function is_api_request(): bool
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

    return str_contains($script, '/api/');
}

function json_response(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function request_json(): array
{
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > 32768) {
        json_response(['ok' => false, 'error' => 'Request body is too large.'], 413);
    }

    $raw = file_get_contents('php://input', false, null, 0, 32769);
    if ($raw === false || $raw === '') {
        return $_POST;
    }
    if (strlen($raw) > 32768) {
        json_response(['ok' => false, 'error' => 'Request body is too large.'], 413);
    }

    try {
        $data = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        json_response(['ok' => false, 'error' => 'Invalid JSON body.'], 400);
    }

    if (!is_array($data)) {
        json_response(['ok' => false, 'error' => 'JSON body must be an object.'], 400);
    }

    return $data;
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        if (is_api_request()) {
            json_response(['ok' => false, 'error' => 'Method not allowed.'], 405);
        }
        http_response_code(405);
        exit('Method not allowed.');
    }
}

function rate_limit(string $action, int $limit, int $windowSeconds, bool $includeIp = true): void
{
    $windowSeconds = max(1, $windowSeconds);
    $identity = $includeIp
        ? $action . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown')
        : $action;
    $key = hash_hmac(
        'sha256',
        $identity,
        APP_KEY
    );
    $sql = sprintf(
        "INSERT INTO rate_limits(bucket, attempts, window_started_at)
         VALUES(?, 1, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            attempts = IF(
                window_started_at <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d SECOND),
                1,
                LEAST(attempts + 1, 2147483647)
            ),
            window_started_at = IF(
                window_started_at <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d SECOND),
                UTC_TIMESTAMP(),
                window_started_at
            )",
        $windowSeconds,
        $windowSeconds
    );
    $statement = db()->prepare($sql);
    $statement->execute([$key]);

    $check = db()->prepare('SELECT attempts FROM rate_limits WHERE bucket = ?');
    $check->execute([$key]);
    if ((int) $check->fetchColumn() > $limit) {
        throw new HttpException('Too many attempts. Please wait and try again.', 429);
    }

    if (random_int(1, 100) === 1) {
        db()->exec('DELETE FROM rate_limits WHERE window_started_at < DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)');
    }
}

function format_credits(int|string $amount): string
{
    return number_format((int) $amount) . ' CR';
}

function level_from_xp(int $xp): int
{
    return min(100, intdiv(max(0, $xp), 1000) + 1);
}

function add_wallet_entry(
    PDO $pdo,
    int $userId,
    int $amount,
    string $type,
    ?string $referenceType = null,
    ?int $referenceId = null
): void {
    $statement = $pdo->prepare(
        'INSERT INTO wallet_transactions(user_id, amount, type, reference_type, reference_id)
         VALUES(?, ?, ?, ?, ?)'
    );
    $statement->execute([$userId, $amount, $type, $referenceType, $referenceId]);
}

function safe_return_path(?string $path, string $fallback = '/casino.php'): string
{
    if ($path === null || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
        return $fallback;
    }

    return preg_match('#^/[A-Za-z0-9_./?=&-]*$#', $path) === 1 ? $path : $fallback;
}

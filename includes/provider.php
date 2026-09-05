<?php

declare(strict_types=1);

final class ProviderRequestException extends RuntimeException
{
    public function __construct(string $message, public readonly int $statusCode = 400)
    {
        parent::__construct($message);
    }
}

function provider_mode(): string
{
    return (string) (app_config()['provider_mode'] ?? 'demo');
}

function provider_wants_json(): bool
{
    return str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json')
        || strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

function provider_schema_available(): bool
{
    if (!db_available()) {
        return false;
    }

    try {
        db()->query('SELECT 1 FROM providers LIMIT 1');
        db()->query('SELECT 1 FROM games LIMIT 1');
        return true;
    } catch (Throwable) {
        return false;
    }
}

function provider_demo_catalog(): array
{
    return [
        ['id' => 0, 'provider_id' => 0, 'provider_slug' => 'neon-demo', 'provider_name' => 'Neon Demo Aggregator', 'provider_game_id' => 'NR-SLOT-001', 'slug' => 'neon-reels', 'title' => 'Neon Reels', 'category' => 'slots', 'description' => 'A local three-reel sandbox game from the fake catalog.', 'thumbnail_url' => '/assets/provider-game.svg', 'min_bet' => 10, 'max_bet' => 1000, 'is_enabled' => 1, 'provider_enabled' => 1, 'health_status' => 'healthy', 'adapter' => 'demo'],
        ['id' => 0, 'provider_id' => 0, 'provider_slug' => 'neon-demo', 'provider_name' => 'Neon Demo Aggregator', 'provider_game_id' => 'NR-LIVE-001', 'slug' => 'royale-live-demo', 'title' => 'Royale Live Demo', 'category' => 'live', 'description' => 'A simulated table feed with no dealer or external stream.', 'thumbnail_url' => '/assets/provider-game.svg', 'min_bet' => 10, 'max_bet' => 1000, 'is_enabled' => 1, 'provider_enabled' => 1, 'health_status' => 'healthy', 'adapter' => 'demo'],
        ['id' => 0, 'provider_id' => 0, 'provider_slug' => 'neon-demo', 'provider_name' => 'Neon Demo Aggregator', 'provider_game_id' => 'NR-INSTANT-001', 'slug' => 'rocket-rush', 'title' => 'Rocket Rush', 'category' => 'instant', 'description' => 'A fast local multiplier round for demo credits.', 'thumbnail_url' => '/assets/provider-game.svg', 'min_bet' => 10, 'max_bet' => 1000, 'is_enabled' => 1, 'provider_enabled' => 1, 'health_status' => 'healthy', 'adapter' => 'demo'],
    ];
}

function provider_catalog(array $filters = [], bool $includeDisabled = false): array
{
    $search = trim(substr((string) ($filters['search'] ?? ''), 0, 100));
    $category = trim(substr((string) ($filters['category'] ?? ''), 0, 30));
    $providerSlug = trim(substr((string) ($filters['provider'] ?? ''), 0, 80));

    if (!provider_schema_available()) {
        if ($includeDisabled) {
            return [];
        }

        return array_values(array_filter(
            provider_demo_catalog(),
            static function (array $game) use ($search, $category, $providerSlug): bool {
                $matchesSearch = $search === '' || str_contains(strtolower($game['title'] . ' ' . $game['description']), strtolower($search));
                $matchesCategory = $category === '' || $game['category'] === $category;
                $matchesProvider = $providerSlug === '' || $game['provider_slug'] === $providerSlug;
                return $matchesSearch && $matchesCategory && $matchesProvider;
            }
        ));
    }

    $where = [];
    $params = [];
    if (!$includeDisabled) {
        $where[] = 'p.is_enabled = 1';
        $where[] = 'g.is_enabled = 1';
    }
    if ($search !== '') {
        $where[] = '(g.title LIKE ? OR g.description LIKE ? OR g.provider_game_id LIKE ?)';
        $like = '%' . $search . '%';
        array_push($params, $like, $like, $like);
    }
    if ($category !== '') {
        $where[] = 'g.category = ?';
        $params[] = $category;
    }
    if ($providerSlug !== '') {
        $where[] = 'p.slug = ?';
        $params[] = $providerSlug;
    }

    $sql = "SELECT g.id, g.provider_id, g.provider_game_id, g.slug, g.title, g.category,
            g.description, g.thumbnail_url, g.is_enabled, g.min_bet, g.max_bet,
            p.slug AS provider_slug, p.name AS provider_name, p.is_enabled AS provider_enabled,
            p.health_status, p.adapter
        FROM games g
        JOIN providers p ON p.id = g.provider_id"
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
        . ' ORDER BY g.sort_order, g.title LIMIT 200';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function provider_public_config(): array
{
    $providers = [];
    if (provider_schema_available()) {
        $providers = db()->query("SELECT slug, name, integration_type, environment, health_status
            FROM providers
            WHERE is_enabled = 1
            ORDER BY name")->fetchAll();
    } else {
        $providers = [[
            'slug' => 'neon-demo',
            'name' => 'Neon Demo Aggregator',
            'integration_type' => 'demo',
            'environment' => 'demo',
            'health_status' => 'healthy',
        ]];
    }

    return [
        'mode' => provider_mode(),
        'currency' => 'CR',
        'real_money' => false,
        'external_provider_calls' => false,
        'webhooks_configured' => trim((string) (app_config()['provider_webhook_secret'] ?? '')) !== '',
        'providers' => $providers,
    ];
}

function provider_redact(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }

    $safe = [];
    foreach ($value as $key => $item) {
        $name = strtolower((string) $key);
        if (preg_match('/secret|password|token|credential|signature|api.?key/', $name)) {
            $safe[$key] = '[redacted]';
        } else {
            $safe[$key] = provider_redact($item);
        }
    }

    return $safe;
}

function provider_json_summary(mixed $value): string
{
    $json = json_encode(provider_redact($value), JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return '{}';
    }

    return substr($json, 0, 4000);
}

function provider_log_api(
    ?int $providerId,
    string $direction,
    string $endpoint,
    string $method,
    ?string $requestId,
    int $statusCode,
    mixed $request = [],
    mixed $response = [],
    int $durationMs = 0
): void {
    if (!provider_schema_available()) {
        return;
    }

    try {
        $stmt = db()->prepare('INSERT INTO provider_api_logs(
            provider_id,direction,endpoint,http_method,request_id,status_code,duration_ms,request_summary,response_summary
        ) VALUES(?,?,?,?,?,?,?,?,?)');
        $stmt->execute([
            $providerId,
            $direction,
            substr($endpoint, 0, 190),
            substr(strtoupper($method), 0, 10),
            $requestId !== null ? substr($requestId, 0, 140) : null,
            $statusCode,
            max(0, $durationMs),
            provider_json_summary($request),
            provider_json_summary($response),
        ]);
    } catch (Throwable) {
        // Logging must not break a player or admin request.
    }
}

function provider_admin_snapshot(array $filters = []): array
{
    $empty = [
        'ready' => false,
        'providers' => [],
        'games' => [],
        'logs' => [],
        'sessions' => [],
        'transactions' => [],
        'jackpots' => [],
        'stats' => ['providers' => 0, 'enabled_games' => 0, 'active_sessions' => 0, 'rounds' => 0],
    ];
    if (!provider_schema_available()) {
        return $empty;
    }

    $providers = db()->query("SELECT p.*,
            (SELECT COUNT(*) FROM games g WHERE g.provider_id = p.id) AS game_count,
            (SELECT COUNT(*) FROM games g WHERE g.provider_id = p.id AND g.is_enabled = 1) AS enabled_game_count,
            (SELECT COUNT(*) FROM game_sessions gs WHERE gs.provider_id = p.id AND gs.status = 'active' AND gs.expires_at > NOW()) AS active_session_count
        FROM providers p
        ORDER BY p.is_enabled DESC, p.name")->fetchAll();

    $games = provider_catalog($filters, true);
    $stats = db()->query("SELECT
        (SELECT COUNT(*) FROM providers) AS providers,
        (SELECT COUNT(*) FROM games g JOIN providers p ON p.id=g.provider_id WHERE g.is_enabled=1 AND p.is_enabled=1) AS enabled_games,
        (SELECT COUNT(*) FROM game_sessions WHERE status='active' AND expires_at > NOW()) AS active_sessions,
        (SELECT COUNT(*) FROM game_rounds) AS rounds")->fetch() ?: $empty['stats'];
    $logs = db()->query("SELECT l.direction, l.endpoint, l.http_method, l.request_id, l.status_code,
            l.duration_ms, l.created_at, p.name AS provider_name
        FROM provider_api_logs l
        LEFT JOIN providers p ON p.id=l.provider_id
        ORDER BY l.created_at DESC, l.id DESC
        LIMIT 40")->fetchAll();
    $sessions = db()->query("SELECT gs.public_id, gs.status, gs.expires_at, gs.last_activity_at,
            u.username, g.title AS game_title, p.name AS provider_name
        FROM game_sessions gs
        JOIN users u ON u.id=gs.user_id
        JOIN games g ON g.id=gs.game_id
        JOIN providers p ON p.id=gs.provider_id
        ORDER BY gs.created_at DESC
        LIMIT 30")->fetchAll();
    $transactions = db()->query("SELECT pt.external_transaction_id, pt.transaction_type, pt.amount,
            pt.status, pt.created_at, u.username, g.title AS game_title, p.name AS provider_name
        FROM provider_transactions pt
        JOIN users u ON u.id=pt.user_id
        JOIN game_sessions gs ON gs.id=pt.game_session_id
        JOIN games g ON g.id=gs.game_id
        JOIN providers p ON p.id=pt.provider_id
        ORDER BY pt.created_at DESC, pt.id DESC
        LIMIT 30")->fetchAll();
    $jackpots = db()->query("SELECT j.name, j.amount, j.is_active, j.updated_at,
            p.name AS provider_name, g.title AS game_title
        FROM jackpots j
        LEFT JOIN providers p ON p.id=j.provider_id
        LEFT JOIN games g ON g.id=j.game_id
        ORDER BY j.amount DESC")->fetchAll();

    return [
        'ready' => true,
        'providers' => $providers,
        'games' => $games,
        'logs' => $logs,
        'sessions' => $sessions,
        'transactions' => $transactions,
        'jackpots' => $jackpots,
        'stats' => $stats,
    ];
}

function provider_set_enabled(int $providerId, bool $enabled): void
{
    if (!provider_schema_available()) {
        throw new InvalidArgumentException('Import the provider schema before managing providers.');
    }

    $stmt = db()->prepare('UPDATE providers SET is_enabled = ?, health_status = ? WHERE id = ?');
    $stmt->execute([$enabled ? 1 : 0, $enabled ? 'unconfigured' : 'offline', $providerId]);
    if ($stmt->rowCount() !== 1) {
        throw new InvalidArgumentException('Provider not found or already in that state.');
    }
}

function provider_set_game_enabled(int $gameId, bool $enabled): void
{
    if (!provider_schema_available()) {
        throw new InvalidArgumentException('Import the provider schema before managing games.');
    }

    $stmt = db()->prepare('UPDATE games SET is_enabled = ? WHERE id = ?');
    $stmt->execute([$enabled ? 1 : 0, $gameId]);
    if ($stmt->rowCount() !== 1) {
        throw new InvalidArgumentException('Game not found or already in that state.');
    }
}

function provider_update_settings(int $providerId, string $apiBaseUrl, string $credentialHint): void
{
    $apiBaseUrl = trim($apiBaseUrl);
    $credentialHint = trim($credentialHint);
    if ($apiBaseUrl !== '') {
        $parts = parse_url($apiBaseUrl);
        if (!filter_var($apiBaseUrl, FILTER_VALIDATE_URL) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw new InvalidArgumentException('Provider base URLs must be valid HTTPS URLs.');
        }
    }
    if ($credentialHint === '' || strlen($credentialHint) > 190 || strlen($apiBaseUrl) > 255) {
        throw new InvalidArgumentException('Enter a credential status of 190 characters or fewer.');
    }

    $stmt = db()->prepare('UPDATE providers SET api_base_url = ?, credential_hint = ? WHERE id = ?');
    $stmt->execute([$apiBaseUrl !== '' ? $apiBaseUrl : null, $credentialHint, $providerId]);
    if ($stmt->rowCount() !== 1) {
        throw new InvalidArgumentException('Provider not found or settings are unchanged.');
    }
}

function provider_check_health(int $providerId): string
{
    $stmt = db()->prepare('SELECT adapter, is_enabled FROM providers WHERE id = ?');
    $stmt->execute([$providerId]);
    $provider = $stmt->fetch();
    if (!$provider) {
        throw new InvalidArgumentException('Provider not found.');
    }

    $status = (int) $provider['is_enabled'] !== 1
        ? 'offline'
        : ($provider['adapter'] === 'demo' ? 'healthy' : 'unconfigured');
    $update = db()->prepare('UPDATE providers SET health_status = ?, last_health_at = NOW() WHERE id = ?');
    $update->execute([$status, $providerId]);
    provider_log_api($providerId, 'internal', '/provider/health', 'CHECK', null, 200, [], ['status' => $status]);

    return $status;
}

function provider_create_session(int $userId, int $gameId): array
{
    if (provider_mode() !== 'demo') {
        throw new InvalidArgumentException('Only the built-in demo provider is available in this build.');
    }
    if (!provider_schema_available()) {
        throw new InvalidArgumentException('The provider schema has not been imported.');
    }

    $stmt = db()->prepare("SELECT g.id, g.provider_id, g.title, g.slug, g.is_enabled,
            p.name AS provider_name, p.slug AS provider_slug, p.adapter, p.is_enabled AS provider_enabled
        FROM games g
        JOIN providers p ON p.id = g.provider_id
        WHERE g.id = ?");
    $stmt->execute([$gameId]);
    $game = $stmt->fetch();
    if (!$game || (int) $game['is_enabled'] !== 1 || (int) $game['provider_enabled'] !== 1) {
        throw new InvalidArgumentException('That provider game is unavailable.');
    }
    if ($game['adapter'] !== 'demo') {
        throw new InvalidArgumentException('External provider adapters are placeholders and cannot be launched.');
    }

    $balanceStmt = db()->prepare('SELECT balance FROM users WHERE id = ?');
    $balanceStmt->execute([$userId]);
    $balance = $balanceStmt->fetchColumn();
    if ($balance === false) {
        throw new InvalidArgumentException('Player account not found.');
    }

    $publicId = bin2hex(random_bytes(16));
    $token = bin2hex(random_bytes(32));
    $insert = db()->prepare("INSERT INTO game_sessions(
        public_id,token_hash,user_id,provider_id,game_id,status,balance_snapshot,expires_at,last_activity_at
    ) VALUES(?,?,?,?,?,'active',?,DATE_ADD(NOW(),INTERVAL 30 MINUTE),NOW())");
    $insert->execute([$publicId, hash('sha256', $token), $userId, $game['provider_id'], $gameId, (int) $balance]);
    provider_log_api(
        (int) $game['provider_id'],
        'outbound',
        '/provider/launch.php',
        'POST',
        'launch:' . $publicId,
        201,
        ['game_id' => $gameId],
        ['session_id' => $publicId, 'adapter' => 'demo']
    );

    return $game + ['public_id' => $publicId, 'token' => $token];
}

function provider_session_by_token(string $token, int $userId): ?array
{
    if (!provider_schema_available() || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $stmt = db()->prepare("SELECT gs.id, gs.public_id, gs.status, gs.balance_snapshot, gs.expires_at,
            gs.last_activity_at, g.id AS game_id, g.title, g.slug AS game_slug, g.category,
            g.description, g.thumbnail_url, g.min_bet, g.max_bet, g.is_enabled AS game_enabled,
            p.id AS provider_id, p.name AS provider_name, p.slug AS provider_slug, p.adapter,
            p.is_enabled AS provider_enabled, p.health_status,
            (gs.expires_at > NOW()) AS is_unexpired
        FROM game_sessions gs
        JOIN games g ON g.id=gs.game_id
        JOIN providers p ON p.id=gs.provider_id
        WHERE gs.token_hash=? AND gs.user_id=?");
    $stmt->execute([hash('sha256', $token), $userId]);
    $session = $stmt->fetch();
    if (!$session) {
        return null;
    }
    if ($session['status'] === 'active' && (int) $session['is_unexpired'] !== 1) {
        $update = db()->prepare("UPDATE game_sessions SET status='expired' WHERE id=? AND status='active'");
        $update->execute([$session['id']]);
        $session['status'] = 'expired';
    }

    return $session;
}

function provider_round_outcome(string $category, int $bet): array
{
    $roll = random_int(1, 10000);
    $multipliers = match ($category) {
        'slots' => [[5200, 0], [8000, 1], [9500, 2], [10000, 5]],
        'instant' => [[5700, 0], [8500, 1], [9700, 2], [10000, 4]],
        'live', 'table', 'cards' => [[5100, 0], [8200, 1], [9700, 2], [10000, 3]],
        default => [[5500, 0], [8500, 1], [10000, 2]],
    };
    $multiplier = 0;
    foreach ($multipliers as [$ceiling, $candidate]) {
        if ($roll <= $ceiling) {
            $multiplier = $candidate;
            break;
        }
    }
    $payout = $bet * $multiplier;
    $label = $multiplier === 0 ? 'No win' : ($multiplier === 1 ? 'Stake returned' : $multiplier . 'x demo win');

    return ['payout' => $payout, 'result' => $label . ' · sandbox roll ' . $roll, 'multiplier' => $multiplier];
}

function provider_play_round(int $userId, string $token, int $bet, string $requestId): array
{
    $requestId = strtolower(trim($requestId));
    if (!preg_match('/^[a-z0-9_-]{16,80}$/', $requestId)) {
        throw new InvalidArgumentException('Invalid round request ID.');
    }
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new InvalidArgumentException('Invalid game session.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $sessionStmt = $pdo->prepare("SELECT id, public_id, status, expires_at, game_id, provider_id
            FROM game_sessions
            WHERE token_hash=? AND user_id=?
            FOR UPDATE");
        $sessionStmt->execute([hash('sha256', $token), $userId]);
        $session = $sessionStmt->fetch();
        if (!$session) {
            throw new InvalidArgumentException('Game session not found.');
        }

        $configStmt = $pdo->prepare("SELECT g.slug AS game_slug, g.title, g.category, g.min_bet, g.max_bet,
                g.is_enabled AS game_enabled, p.name AS provider_name, p.adapter,
                p.is_enabled AS provider_enabled
            FROM games g
            JOIN providers p ON p.id=g.provider_id
            WHERE g.id=? AND p.id=?
            LOCK IN SHARE MODE");
        $configStmt->execute([$session['game_id'], $session['provider_id']]);
        $config = $configStmt->fetch();
        if (!$config) {
            throw new InvalidArgumentException('Provider game configuration is unavailable.');
        }
        $session = array_merge($session, $config);

        $existingStmt = $pdo->prepare("SELECT gr.external_round_id, gr.bet, gr.payout, gr.result
            FROM game_rounds gr
            WHERE gr.game_session_id=? AND gr.external_round_id=?");
        $existingStmt->execute([$session['id'], $requestId]);
        $existing = $existingStmt->fetch();
        if ($existing) {
            $balanceStmt = $pdo->prepare('SELECT balance FROM users WHERE id=?');
            $balanceStmt->execute([$userId]);
            $balance = (int) $balanceStmt->fetchColumn();
            $pdo->commit();
            return $existing + ['balance' => $balance, 'replayed' => true];
        }

        if ($session['status'] !== 'active') {
            throw new InvalidArgumentException('This game session is no longer active.');
        }
        $expiryStmt = $pdo->prepare('SELECT (? > NOW())');
        $expiryStmt->execute([$session['expires_at']]);
        if ((int) $expiryStmt->fetchColumn() !== 1) {
            $pdo->prepare("UPDATE game_sessions SET status='expired' WHERE id=?")->execute([$session['id']]);
            $pdo->commit();
            throw new InvalidArgumentException('This game session has expired. Launch the game again.');
        }
        if ((int) $session['provider_enabled'] !== 1 || (int) $session['game_enabled'] !== 1 || $session['adapter'] !== 'demo') {
            throw new InvalidArgumentException('This demo provider or game is currently disabled.');
        }
        if ($bet < (int) $session['min_bet'] || $bet > (int) $session['max_bet']) {
            throw new InvalidArgumentException('Choose a bet between ' . $session['min_bet'] . ' and ' . $session['max_bet'] . ' credits.');
        }

        $userStmt = $pdo->prepare('SELECT balance, xp FROM users WHERE id=? FOR UPDATE');
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        if (!$user || (int) $user['balance'] < $bet) {
            throw new InvalidArgumentException('Not enough balance for that demo round.');
        }

        $outcome = provider_round_outcome((string) $session['category'], $bet);
        $balanceBefore = (int) $user['balance'];
        $balanceAfterBet = $balanceBefore - $bet;
        $balanceAfter = $balanceAfterBet + (int) $outcome['payout'];
        $xpGain = min(250, max(10, intdiv($bet, 10) + ((int) $outcome['payout'] > 0 ? 20 : 5)));
        $newXp = (int) $user['xp'] + $xpGain;

        $updateUser = $pdo->prepare('UPDATE users SET balance=?, xp=?, level=? WHERE id=?');
        $updateUser->execute([$balanceAfter, $newXp, calculate_level($newXp), $userId]);

        $roundInsert = $pdo->prepare("INSERT INTO game_rounds(
            game_session_id,external_round_id,bet,payout,result,status,settled_at
        ) VALUES(?,?,?,?,?,'settled',NOW())");
        $roundInsert->execute([$session['id'], $requestId, $bet, $outcome['payout'], $outcome['result']]);
        $roundId = (int) $pdo->lastInsertId();

        $providerTx = $pdo->prepare("INSERT INTO provider_transactions(
            provider_id,game_session_id,game_round_id,user_id,external_transaction_id,transaction_type,amount,status,payload_json
        ) VALUES(?,?,?,?,?,?,?,'completed',?)");
        $providerTx->execute([
            $session['provider_id'],
            $session['id'],
            $roundId,
            $userId,
            $session['public_id'] . ':' . $requestId . ':bet',
            'bet',
            $bet,
            provider_json_summary(['round_id' => $requestId]),
        ]);
        $betTransactionId = (int) $pdo->lastInsertId();
        $walletTx = $pdo->prepare("INSERT INTO wallet_transactions(
            user_id,provider_transaction_id,game_session_id,direction,amount,balance_before,balance_after,reason,idempotency_key
        ) VALUES(?,?,?,'debit',?,?,?,'provider_bet',?)");
        $walletTx->execute([$userId, $betTransactionId, $session['id'], $bet, $balanceBefore, $balanceAfterBet, $session['public_id'] . ':' . $requestId . ':wallet:bet']);

        if ((int) $outcome['payout'] > 0) {
            $providerTx->execute([
                $session['provider_id'],
                $session['id'],
                $roundId,
                $userId,
                $session['public_id'] . ':' . $requestId . ':win',
                'win',
                $outcome['payout'],
                provider_json_summary(['round_id' => $requestId]),
            ]);
            $winTransactionId = (int) $pdo->lastInsertId();
            $creditTx = $pdo->prepare("INSERT INTO wallet_transactions(
                user_id,provider_transaction_id,game_session_id,direction,amount,balance_before,balance_after,reason,idempotency_key
            ) VALUES(?,?,?,'credit',?,?,?,'provider_win',?)");
            $creditTx->execute([$userId, $winTransactionId, $session['id'], $outcome['payout'], $balanceAfterBet, $balanceAfter, $session['public_id'] . ':' . $requestId . ':wallet:win']);
        }

        $history = $pdo->prepare('INSERT INTO game_transactions(user_id,game,bet,payout,result) VALUES(?,?,?,?,?)');
        $history->execute([
            $userId,
            substr('provider:' . $session['game_slug'], 0, 40),
            $bet,
            $outcome['payout'],
            $outcome['result'],
        ]);
        $pdo->prepare('UPDATE game_sessions SET last_activity_at=NOW() WHERE id=?')->execute([$session['id']]);
        $pdo->prepare('UPDATE jackpots SET amount=amount+? WHERE provider_id=? AND is_active=1')
            ->execute([max(1, intdiv($bet, 100)), $session['provider_id']]);
        $pdo->commit();

        provider_log_api(
            (int) $session['provider_id'],
            'internal',
            '/provider/wallet.php',
            'POST',
            'round:' . $requestId,
            200,
            ['session_id' => $session['public_id'], 'bet' => $bet],
            ['payout' => $outcome['payout'], 'balance' => $balanceAfter]
        );

        return [
            'external_round_id' => $requestId,
            'bet' => $bet,
            'payout' => (int) $outcome['payout'],
            'result' => $outcome['result'],
            'balance' => $balanceAfter,
            'xp_gain' => $xpGain,
            'replayed' => false,
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function provider_close_session(int $userId, string $token): void
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        throw new InvalidArgumentException('Invalid game session.');
    }
    $stmt = db()->prepare("UPDATE game_sessions SET status='closed', last_activity_at=NOW()
        WHERE token_hash=? AND user_id=? AND status='active'");
    $stmt->execute([hash('sha256', $token), $userId]);
}

function provider_handle_webhook(string $endpoint, string $body, array $headers): array
{
    if (!provider_schema_available()) {
        throw new ProviderRequestException('Provider schema unavailable.', 503);
    }
    if (provider_mode() !== 'demo') {
        throw new ProviderRequestException('External provider callbacks are disabled.', 503);
    }

    $providerSlug = strtolower(trim((string) ($headers['provider_id'] ?? '')));
    $timestamp = trim((string) ($headers['timestamp'] ?? ''));
    $signature = strtolower(trim((string) ($headers['signature'] ?? '')));
    if (!preg_match('/^[a-z0-9-]{2,80}$/', $providerSlug) || !ctype_digit($timestamp) || !preg_match('/^[a-f0-9]{64}$/', $signature)) {
        throw new ProviderRequestException('Missing or invalid provider authentication headers.', 401);
    }

    $window = max(30, (int) (app_config()['provider_webhook_window'] ?? 300));
    if (abs(time() - (int) $timestamp) > $window) {
        throw new ProviderRequestException('Webhook timestamp is outside the allowed window.', 401);
    }

    $secret = trim((string) (app_config()['provider_webhook_secret'] ?? ''));
    if ($secret === '') {
        throw new ProviderRequestException('Demo webhook authentication is not configured.', 503);
    }
    $derivedSecret = hash_hmac('sha256', $providerSlug, $secret, true);
    $expected = hash_hmac('sha256', $timestamp . '.' . $body, $derivedSecret);
    if (!hash_equals($expected, $signature)) {
        throw new ProviderRequestException('Invalid webhook signature.', 401);
    }

    $providerStmt = db()->prepare("SELECT id, adapter, is_enabled FROM providers WHERE slug=?");
    $providerStmt->execute([$providerSlug]);
    $provider = $providerStmt->fetch();
    if (!$provider || $provider['adapter'] !== 'demo' || (int) $provider['is_enabled'] !== 1) {
        throw new ProviderRequestException('Provider is unavailable.', 404);
    }

    try {
        $event = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new ProviderRequestException('Webhook body must be valid JSON.', 400);
    }
    if (!is_array($event)) {
        throw new ProviderRequestException('Webhook body must be a JSON object.', 400);
    }
    $eventId = trim((string) ($event['event_id'] ?? ''));
    $eventType = trim((string) ($event['type'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9_-]{8,100}$/', $eventId) || !in_array($eventType, ['health.ping', 'catalog.sync', 'session.closed'], true)) {
        throw new ProviderRequestException('Unsupported webhook event.', 422);
    }

    $requestId = 'webhook:' . hash('sha256', $providerSlug . ':' . $eventId);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $insert = $pdo->prepare("INSERT INTO provider_api_logs(
            provider_id,direction,endpoint,http_method,request_id,status_code,duration_ms,request_summary,response_summary
        ) VALUES(?,'inbound',?,'POST',?,202,0,?,?)");
        $insert->execute([
            $provider['id'],
            substr($endpoint, 0, 190),
            $requestId,
            provider_json_summary($event),
            provider_json_summary(['accepted' => true]),
        ]);

        if ($eventType === 'health.ping') {
            $pdo->prepare("UPDATE providers SET health_status='healthy', last_health_at=NOW() WHERE id=?")
                ->execute([$provider['id']]);
        } elseif ($eventType === 'session.closed') {
            $sessionId = trim((string) ($event['session_id'] ?? ''));
            if (!preg_match('/^[a-f0-9]{32}$/', $sessionId)) {
                throw new ProviderRequestException('A valid session_id is required.', 422);
            }
            $pdo->prepare("UPDATE game_sessions SET status='closed', last_activity_at=NOW()
                WHERE public_id=? AND provider_id=? AND status='active'")
                ->execute([$sessionId, $provider['id']]);
        }

        $pdo->commit();
        return ['accepted' => true, 'duplicate' => false, 'event_id' => $eventId, 'type' => $eventType];
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ((string) $e->getCode() === '23000') {
            return ['accepted' => true, 'duplicate' => true, 'event_id' => $eventId, 'type' => $eventType];
        }
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function provider_health_overview(): array
{
    if (!provider_schema_available()) {
        return ['ready' => false, 'healthy' => 0, 'enabled' => 0];
    }

    $row = db()->query("SELECT
        SUM(is_enabled=1) AS enabled,
        SUM(is_enabled=1 AND health_status='healthy') AS healthy
        FROM providers")->fetch() ?: [];
    return ['ready' => true, 'healthy' => (int) ($row['healthy'] ?? 0), 'enabled' => (int) ($row['enabled'] ?? 0)];
}

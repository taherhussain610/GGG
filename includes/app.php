<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isSecure,
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_name('neon_royale');
    session_start();
}

function send_security_headers(): void
{
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
}

function csrf_token(): string
{
    start_secure_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    start_secure_session();
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(422);
        exit('Invalid CSRF token.');
    }
}

function current_user(): ?array
{
    start_secure_session();
    if (empty($_SESSION['user_id']) || !db_available()) {
        return null;
    }

    static $cachedUserId = null;
    static $user = null;
    $sessionUserId = (int) $_SESSION['user_id'];
    if ($cachedUserId === $sessionUserId && $user !== null) {
        return $user;
    }

    $stmt = db()->prepare('SELECT id, username, email, balance, xp, level, is_admin, last_bonus_at, created_at FROM users WHERE id = ?');
    $stmt->execute([$sessionUserId]);
    $user = $stmt->fetch() ?: null;
    $cachedUserId = $sessionUserId;

    return $user;
}

function refresh_user_session(int $userId): void
{
    start_secure_session();
    $_SESSION['user_id'] = $userId;
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: /login.php');
        exit;
    }
}

function require_admin(): void
{
    $user = current_user();
    if (!$user || (int) $user['is_admin'] !== 1) {
        http_response_code(403);
        exit('Admin access required.');
    }
}

function flash(?string $message = null, string $type = 'info'): ?array
{
    start_secure_session();
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }

    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $flash;
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function fmt_coins(int $value): string
{
    return number_format($value) . ' CR';
}

function level_progress(int $xp): int
{
    return $xp % 500;
}

function calculate_level(int $xp): int
{
    return max(1, intdiv($xp, 500) + 1);
}

function award_xp(PDO $pdo, int $userId, int $xpGain): void
{
    $stmt = $pdo->prepare('SELECT xp FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $currentXp = (int) ($stmt->fetchColumn() ?: 0);
    $newXp = $currentXp + $xpGain;

    $update = $pdo->prepare('UPDATE users SET xp = ?, level = ? WHERE id = ?');
    $update->execute([$newXp, calculate_level($newXp), $userId]);
}

function register_user(string $username, string $email, string $password): void
{
    start_secure_session();
    $username = trim($username);
    $email = trim(strtolower($email));

    if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        throw new InvalidArgumentException('Use a valid username, email and an 8+ character password.');
    }

    $stmt = db()->prepare('INSERT INTO users(username, email, password_hash) VALUES(?,?,?)');
    $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
    session_regenerate_id(true);
    refresh_user_session((int) db()->lastInsertId());
}

function attempt_login(string $email, string $password): bool
{
    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = ?');
    $stmt->execute([trim(strtolower($email))]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    session_regenerate_id(true);
    refresh_user_session((int) $user['id']);
    return true;
}

function logout_user(): void
{
    start_secure_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function game_definitions(): array
{
    return [
        'slots' => ['label' => 'Slots', 'category' => 'slots', 'description' => 'Spin triple neon reels for match bonuses.'],
        'roulette' => ['label' => 'Roulette', 'category' => 'table', 'description' => 'Back red or black in a fast 50/50 spin.'],
        'blackjack' => ['label' => 'Blackjack', 'category' => 'table', 'description' => 'Beat the dealer without busting.'],
        'dice' => ['label' => 'Dice', 'category' => 'instant', 'description' => 'Pick high or low and roll a six-sided die.'],
        'poker' => ['label' => 'Poker Draw', 'category' => 'cards', 'description' => 'Draw five cards and get paid by hand rank.'],
    ];
}

function deck_of_cards(): array
{
    $ranks = ['2', '3', '4', '5', '6', '7', '8', '9', '10', 'J', 'Q', 'K', 'A'];
    $suits = ['♠', '♥', '♦', '♣'];
    $deck = [];
    foreach ($ranks as $rank) {
        foreach ($suits as $suit) {
            $deck[] = $rank . $suit;
        }
    }

    shuffle($deck);
    return $deck;
}

function poker_hand_rank(array $cards): array
{
    $ranks = [];
    $suits = [];
    foreach ($cards as $card) {
        preg_match('/^(10|[2-9JQKA])(.+)$/u', $card, $matches);
        $rank = $matches[1] ?? '2';
        $suit = $matches[2] ?? '♠';
        $ranks[] = $rank;
        $suits[] = $suit;
    }

    $map = ['2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9, '10' => 10, 'J' => 11, 'Q' => 12, 'K' => 13, 'A' => 14];
    $values = array_map(fn(string $rank): int => $map[$rank], $ranks);
    sort($values);
    $counts = array_count_values($ranks);
    rsort($counts);
    $flush = count(array_unique($suits)) === 1;
    $straight = $values === range($values[0], $values[0] + 4);

    if ($straight && $flush) {
        return ['rank' => 'Straight Flush', 'multiplier' => 15];
    }
    if (($counts[0] ?? 0) === 4) {
        return ['rank' => 'Four of a Kind', 'multiplier' => 10];
    }
    if (($counts[0] ?? 0) === 3 && ($counts[1] ?? 0) === 2) {
        return ['rank' => 'Full House', 'multiplier' => 8];
    }
    if ($flush) {
        return ['rank' => 'Flush', 'multiplier' => 6];
    }
    if ($straight) {
        return ['rank' => 'Straight', 'multiplier' => 5];
    }
    if (($counts[0] ?? 0) === 3) {
        return ['rank' => 'Three of a Kind', 'multiplier' => 4];
    }
    if (($counts[0] ?? 0) === 2 && ($counts[1] ?? 0) === 2) {
        return ['rank' => 'Two Pair', 'multiplier' => 3];
    }
    if (($counts[0] ?? 0) === 2) {
        return ['rank' => 'Pair', 'multiplier' => 2];
    }

    return ['rank' => 'High Card', 'multiplier' => 0];
}

function resolve_game(string $game, int $bet, array $input = []): array
{
    return match ($game) {
        'slots' => resolve_slots($bet),
        'roulette' => resolve_roulette($bet, $input['selection'] ?? 'red'),
        'blackjack' => resolve_blackjack($bet),
        'dice' => resolve_dice($bet, $input['selection'] ?? 'high'),
        'poker' => resolve_poker($bet),
        default => throw new InvalidArgumentException('Unknown game.'),
    };
}

function resolve_slots(int $bet): array
{
    $symbols = ['7', 'BAR', 'STAR', 'CROWN', 'GEM', 'CHERRY'];
    $reels = [$symbols[array_rand($symbols)], $symbols[array_rand($symbols)], $symbols[array_rand($symbols)]];
    $counts = array_count_values($reels);
    $best = max($counts);
    $multiplier = 0;
    if ($reels === ['7', '7', '7']) {
        $multiplier = 20;
    } elseif ($best === 3) {
        $multiplier = 8;
    } elseif ($best === 2) {
        $multiplier = 2;
    }

    return [
        'payout' => $bet * $multiplier,
        'result' => 'Reels: ' . implode(' | ', $reels) . ($multiplier > 0 ? " • x{$multiplier} win" : ' • no match'),
    ];
}

function resolve_roulette(int $bet, string $selection): array
{
    $selection = $selection === 'black' ? 'black' : 'red';
    $outcome = random_int(0, 1) === 0 ? 'red' : 'black';
    $won = $selection === $outcome;

    return [
        'payout' => $won ? $bet * 2 : 0,
        'result' => 'Ball landed on ' . ucfirst($outcome) . '. You picked ' . ucfirst($selection) . '.',
    ];
}

function resolve_blackjack(int $bet): array
{
    $player = random_int(15, 23);
    $dealer = random_int(15, 23);
    $won = $player <= 21 && ($dealer > 21 || $player > $dealer);
    $push = $player <= 21 && $dealer <= 21 && $player === $dealer;

    return [
        'payout' => $push ? $bet : ($won ? (int) round($bet * 2.2) : 0),
        'result' => "Player {$player} vs Dealer {$dealer}" . ($push ? ' • push' : ($won ? ' • blackjack win' : ' • dealer wins')),
    ];
}

function resolve_dice(int $bet, string $selection): array
{
    $selection = $selection === 'low' ? 'low' : 'high';
    $roll = random_int(1, 6);
    $won = ($selection === 'high' && $roll >= 4) || ($selection === 'low' && $roll <= 3);

    return [
        'payout' => $won ? $bet * 2 : 0,
        'result' => "Rolled {$roll} on a {$selection} bet.",
    ];
}

function resolve_poker(int $bet): array
{
    $cards = array_slice(deck_of_cards(), 0, 5);
    $rank = poker_hand_rank($cards);

    return [
        'payout' => $bet * $rank['multiplier'],
        'result' => implode(' ', $cards) . ' • ' . $rank['rank'],
    ];
}

function play_game(int $userId, string $game, int $bet, array $input = []): array
{
    if ($bet < 10) {
        throw new InvalidArgumentException('Minimum bet is 10 credits.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT balance, xp FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new RuntimeException('User not found.');
        }
        if ((int) $user['balance'] < $bet) {
            throw new InvalidArgumentException('Not enough balance for that bet.');
        }

        $outcome = resolve_game($game, $bet, $input);
        $xpGain = min(250, max(10, intdiv($bet, 10) + ($outcome['payout'] > 0 ? 20 : 5)));
        $newXp = (int) $user['xp'] + $xpGain;

        $update = $pdo->prepare('UPDATE users SET balance = balance - ? + ?, xp = ?, level = ? WHERE id = ?');
        $update->execute([$bet, $outcome['payout'], $newXp, calculate_level($newXp), $userId]);

        $tx = $pdo->prepare('INSERT INTO game_transactions(user_id, game, bet, payout, result) VALUES(?,?,?,?,?)');
        $tx->execute([$userId, $game, $bet, $outcome['payout'], $outcome['result']]);

        $pdo->commit();
        return $outcome + ['xp_gain' => $xpGain];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function settle_sports(): void
{
    if (!db_available()) {
        return;
    }

    $pdo = db();
    $events = $pdo->query("SELECT id, sport, home_team, away_team, draw_odds, starts_at FROM sports_events WHERE status <> 'finished'")->fetchAll();
    $now = time();
    foreach ($events as $event) {
        $startsAt = strtotime((string) $event['starts_at']);
        if ($startsAt === false) {
            continue;
        }
        if ($startsAt <= $now) {
            $options = $event['draw_odds'] !== null ? ['home', 'draw', 'away'] : ['home', 'away'];
            $winner = $options[array_rand($options)];
            $summary = match ($winner) {
                'home' => $event['home_team'] . ' won the virtual fixture',
                'away' => $event['away_team'] . ' won the virtual fixture',
                default => 'The virtual fixture finished level',
            };
            $update = $pdo->prepare("UPDATE sports_events SET status = 'finished', winner = ?, result_summary = ? WHERE id = ?");
            $update->execute([$winner, $summary, $event['id']]);
        } elseif ($startsAt <= $now + 900) {
            $live = $pdo->prepare("UPDATE sports_events SET status = 'live' WHERE id = ? AND status = 'upcoming'");
            $live->execute([$event['id']]);
        }
    }

    $picks = $pdo->query("SELECT sp.id, sp.user_id, sp.event_id, sp.selection, sp.potential_win, se.winner, se.status
        FROM sports_picks sp
        JOIN sports_events se ON se.id = sp.event_id
        WHERE sp.status = 'open' AND se.status = 'finished'")->fetchAll();

    foreach ($picks as $pick) {
        $won = $pick['selection'] === $pick['winner'];
        $pdo->beginTransaction();
        try {
            $status = $won ? 'won' : 'lost';
            $updatePick = $pdo->prepare('UPDATE sports_picks SET status = ? WHERE id = ? AND status = ?');
            $updatePick->execute([$status, $pick['id'], 'open']);
            if ($updatePick->rowCount() !== 1) {
                $pdo->rollBack();
                continue;
            }
            if ($won) {
                $credit = $pdo->prepare('UPDATE users SET balance = balance + ? WHERE id = ?');
                $credit->execute([(int) $pick['potential_win'], $pick['user_id']]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}

function sports_events(): array
{
    settle_sports();
    if (!db_available()) {
        return [];
    }

    return db()->query('SELECT * FROM sports_events ORDER BY FIELD(status, "live", "upcoming", "finished"), starts_at ASC LIMIT 12')->fetchAll();
}

function place_pick(int $userId, int $eventId, string $selection, int $stake): void
{
    if ($stake < 10) {
        throw new InvalidArgumentException('Minimum stake is 10 credits.');
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $userStmt = $pdo->prepare('SELECT balance FROM users WHERE id = ? FOR UPDATE');
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch();
        if (!$user || (int) $user['balance'] < $stake) {
            throw new InvalidArgumentException('Not enough balance for that pick.');
        }

        $eventStmt = $pdo->prepare('SELECT * FROM sports_events WHERE id = ? FOR UPDATE');
        $eventStmt->execute([$eventId]);
        $event = $eventStmt->fetch();
        if (!$event || $event['status'] === 'finished') {
            throw new InvalidArgumentException('That event is no longer available.');
        }

        $selectionMap = [
            'home' => (float) $event['home_odds'],
            'draw' => $event['draw_odds'] !== null ? (float) $event['draw_odds'] : null,
            'away' => (float) $event['away_odds'],
        ];
        $odds = $selectionMap[$selection] ?? null;
        if ($odds === null) {
            throw new InvalidArgumentException('Invalid market selection.');
        }

        $potentialWin = (int) round($stake * $odds);
        $insert = $pdo->prepare('INSERT INTO sports_picks(user_id, event_id, selection, odds, stake, potential_win) VALUES(?,?,?,?,?,?)');
        $insert->execute([$userId, $eventId, $selection, $odds, $stake, $potentialWin]);
        $debit = $pdo->prepare('UPDATE users SET balance = balance - ? WHERE id = ?');
        $debit->execute([$stake, $userId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function jackpot_totals(): array
{
    if (!db_available()) {
        return ['Mega Jackpot' => 125000, 'Blackjack Rush' => 48000, 'Roulette Royale' => 36000];
    }

    $row = db()->query('SELECT COALESCE(SUM(bet),0) AS turnover FROM game_transactions')->fetch();
    $turnover = (int) ($row['turnover'] ?? 0);

    return [
        'Mega Jackpot' => 125000 + (int) round($turnover * 0.08),
        'Blackjack Rush' => 48000 + (int) round($turnover * 0.03),
        'Roulette Royale' => 36000 + (int) round($turnover * 0.025),
    ];
}

function leaderboard_rows(): array
{
    if (!db_available()) {
        return [];
    }

    return db()->query('SELECT username, balance, xp, level, created_at FROM users ORDER BY balance DESC, xp DESC LIMIT 20')->fetchAll();
}

function claim_bonus(int $userId): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT xp, last_bonus_at, TIMESTAMPDIFF(SECOND, last_bonus_at, NOW()) AS bonus_age_seconds FROM users WHERE id = ? FOR UPDATE');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('User not found.');
        }

        if ($row['bonus_age_seconds'] !== null && (int) $row['bonus_age_seconds'] < 86400) {
            throw new InvalidArgumentException('Daily bonus already claimed in the last 24 hours.');
        }

        $amount = random_int(500, 1500);
        $newXp = (int) $row['xp'] + 50;
        $update = $pdo->prepare('UPDATE users SET balance = balance + ?, xp = ?, level = ?, last_bonus_at = NOW() WHERE id = ?');
        $update->execute([$amount, $newXp, calculate_level($newXp), $userId]);
        $pdo->commit();
        return ['amount' => $amount];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function recent_results(): array
{
    if (!db_available()) {
        return ['sports' => [], 'casino' => []];
    }

    settle_sports();
    $sports = db()->query("SELECT sport, league, home_team, away_team, winner, result_summary, starts_at FROM sports_events WHERE status = 'finished' ORDER BY starts_at DESC LIMIT 8")->fetchAll();
    $casino = db()->query('SELECT game, bet, payout, result, created_at FROM game_transactions ORDER BY created_at DESC LIMIT 12')->fetchAll();
    return ['sports' => $sports, 'casino' => $casino];
}

function user_history(int $userId): array
{
    if (!db_available()) {
        return ['games' => [], 'picks' => []];
    }

    $gameStmt = db()->prepare('SELECT game, bet, payout, result, created_at FROM game_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 12');
    $gameStmt->execute([$userId]);
    $pickStmt = db()->prepare("SELECT sp.selection, sp.stake, sp.potential_win, sp.status, se.home_team, se.away_team, se.sport, sp.created_at
        FROM sports_picks sp
        JOIN sports_events se ON se.id = sp.event_id
        WHERE sp.user_id = ?
        ORDER BY sp.created_at DESC LIMIT 12");
    $pickStmt->execute([$userId]);

    return ['games' => $gameStmt->fetchAll(), 'picks' => $pickStmt->fetchAll()];
}

function admin_snapshot(): array
{
    if (!db_available()) {
        return ['stats' => [], 'users' => []];
    }

    $stats = db()->query("SELECT
        (SELECT COUNT(*) FROM users) AS users_total,
        (SELECT COALESCE(SUM(balance),0) FROM users) AS balances_total,
        (SELECT COALESCE(SUM(bet),0) FROM game_transactions) AS bet_total,
        (SELECT COALESCE(SUM(payout),0) FROM game_transactions) AS payout_total,
        (SELECT COUNT(*) FROM sports_picks) AS picks_total")->fetch() ?: [];
    $users = db()->query('SELECT username, email, balance, xp, level, is_admin, last_bonus_at FROM users ORDER BY created_at DESC LIMIT 20')->fetchAll();

    return ['stats' => $stats, 'users' => $users];
}

function setup_notice(): ?string
{
    return db_available() ? null : 'Database connection not configured yet. Update /config/config.php (or DB_* environment variables), import /database.sql, then refresh.';
}

function logout_button(): void
{
    ?>
    <form method="post" action="/logout.php" class="logout-form">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <button type="submit" class="secondary">Logout</button>
    </form>
    <?php
}

function render_header(string $title, string $active = 'casino'): void
{
    $user = current_user();
    $flash = flash();
    $notice = setup_notice();
    $nav = [
        'casino' => ['/index.php', 'Casino'],
        'sports' => ['/sports.php', 'Sports'],
        'results' => ['/results.php', 'Results'],
        'leaderboard' => ['/leaderboard.php', 'Leaderboard'],
        'profile' => ['/profile.php', 'Profile'],
    ];
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e(app_config()['app_name']) ?></title>
    <link rel="stylesheet" href="/assets/app.css">
    <script defer src="/assets/app.js"></script>
</head>
<body data-auth="<?= $user ? '1' : '0' ?>">
<header class="topbar">
    <div>
        <a class="brand" href="/index.php">Neon Royale</a>
        <p class="subbrand">Play-money casino · no real-money wagering</p>
    </div>
    <nav class="nav-links">
        <?php foreach ($nav as $key => [$href, $label]): ?>
            <a class="<?= $active === $key ? 'active' : '' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
        <?php if ($user && (int) $user['is_admin'] === 1): ?>
            <a class="<?= $active === 'admin' ? 'active' : '' ?>" href="/admin.php">Admin</a>
        <?php endif; ?>
    </nav>
    <div class="account-chip">
        <?php if ($user): ?>
            <span class="balance-label">Balance</span>
            <strong id="balance-display" data-balance><?= fmt_coins((int) $user['balance']) ?></strong>
            <?php logout_button(); ?>
        <?php else: ?>
            <a href="/login.php">Login</a>
            <a href="/register.php">Register</a>
        <?php endif; ?>
    </div>
</header>
<main class="page-shell">
    <?php if ($notice): ?><div class="banner banner-warning"><?= e($notice) ?></div><?php endif; ?>
    <?php if ($flash): ?><div class="banner banner-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
    <?php
}

function render_footer(string $active = 'casino'): void
{
    $links = [
        'casino' => ['/index.php', 'Casino'],
        'sports' => ['/sports.php', 'Sports'],
        'results' => ['/results.php', 'Results'],
        'profile' => ['/profile.php', 'Profile'],
    ];
    ?>
</main>
<nav class="mobile-nav">
    <?php foreach ($links as $key => [$href, $label]): ?>
        <a class="<?= $active === $key ? 'active' : '' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</nav>
</body>
</html>
<?php
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

<?php

declare(strict_types=1);

if (!isset($gameKey) || !is_string($gameKey)) {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/includes/bootstrap.php';
require ROOT_PATH . '/includes/game_engine.php';

$user = require_auth();
$games = [
    'slots' => [
        'title' => 'Nova Reels',
        'eyebrow' => 'Three-reel slots',
        'description' => 'Match symbols across the centre line. A single cherry returns the stake; pairs and triples pay more.',
        'button' => 'Spin reels',
        'visual' => ['★', '♦', '7'],
        'rules' => ['Single cherry: 1×', 'Any pair: 2×', 'Three symbols: 4×–15×', 'Three sevens: jackpot'],
    ],
    'roulette' => [
        'title' => 'Orbit Roulette',
        'eyebrow' => 'Single-zero wheel',
        'description' => 'Choose a colour, parity, or exact number. Zero is neither even nor odd.',
        'button' => 'Spin wheel',
        'visual' => ['0', '17', '32'],
        'rules' => ['Red / black: 2×', 'Even / odd: 2×', 'Exact number: 36×', 'Exact zero: jackpot'],
    ],
    'blackjack' => [
        'title' => 'Midnight 21',
        'eyebrow' => 'Quick blackjack',
        'description' => 'The server plays both hands to 17. Highest total up to 21 wins; equal totals push.',
        'button' => 'Deal round',
        'visual' => ['A♠', 'K♦', '21'],
        'rules' => ['Win: 2×', 'Natural blackjack: 2.5×', 'Push: stake returned', 'Dealer stands on 17'],
    ],
    'dice' => [
        'title' => 'Pulse Dice',
        'eyebrow' => 'High or low',
        'description' => 'Call 2–6 low or 8–12 high. A total of seven is neutral and does not pay.',
        'button' => 'Roll dice',
        'visual' => ['⚂', '⚄', '⚅'],
        'rules' => ['Correct call: 2×', 'Seven: no payout', 'Double six on high: jackpot', 'Two secure random dice'],
    ],
    'poker' => [
        'title' => 'Neon Draw',
        'eyebrow' => 'Five-card draw',
        'description' => 'Deal five cards, hold the ones you want, then draw once. Jacks or better begins the paytable.',
        'button' => 'Deal five cards',
        'visual' => ['J♣', 'Q♥', 'K♠'],
        'rules' => ['Jacks or better: 2×', 'Straight: 5×', 'Full house: 10×', 'Royal flush: jackpot'],
    ],
];

if (!isset($games[$gameKey])) {
    http_response_code(404);
    exit;
}

$game = $games[$gameKey];
$pendingPoker = null;
$pendingHand = [];
if ($gameKey === 'poker') {
    $statement = db()->prepare(
        'SELECT id, bet, state_json FROM game_transactions
         WHERE user_id = ? AND game = "poker" AND status = "pending"
         ORDER BY id DESC LIMIT 1'
    );
    $statement->execute([(int) $user['id']]);
    $pendingPoker = $statement->fetch();
    if ($pendingPoker !== false) {
        try {
            $state = json_decode((string) $pendingPoker['state_json'], true, 32, JSON_THROW_ON_ERROR);
            $pendingHand = (new GameEngine())->publicPokerHand($state);
        } catch (JsonException) {
            $pendingPoker = null;
        }
    }
}

$pageTitle = $game['title'];
$activePage = 'casino';
require ROOT_PATH . '/partials/header.php';
?>
<section class="page-shell game-page">
    <a class="back-link" href="<?= e(url('/casino.php')) ?>">← Back to casino</a>
    <div class="game-layout">
        <article class="game-stage panel">
            <header class="game-stage-header">
                <div>
                    <p class="eyebrow"><?= e($game['eyebrow']) ?></p>
                    <h1><?= e($game['title']) ?></h1>
                </div>
                <span class="fair-badge">Server RNG</span>
            </header>

            <div class="game-display" data-game-display aria-live="polite">
                <?php if ($gameKey === 'poker' && $pendingPoker !== false): ?>
                    <div class="poker-hand" data-poker-hand>
                        <?php foreach ($pendingHand as $index => $card): ?>
                            <label class="playing-card">
                                <input type="checkbox" value="<?= $index ?>" data-poker-hold>
                                <span><?= e($card) ?></span>
                                <small>Hold</small>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <strong data-result-title>Draw is ready</strong>
                    <p data-result-message>Select the cards to hold, then draw once.</p>
                <?php else: ?>
                    <div class="game-visual-row" data-result-symbols>
                        <?php foreach ($game['visual'] as $symbol): ?>
                            <span><?= e($symbol) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <strong data-result-title>Place a virtual-credit bet</strong>
                    <p data-result-message><?= e($game['description']) ?></p>
                <?php endif; ?>
            </div>

            <?php if ($gameKey === 'poker'): ?>
                <form class="game-controls" data-poker-form
                      data-stage="<?= $pendingPoker === false ? 'deal' : 'draw' ?>"
                      data-round-id="<?= $pendingPoker === false ? '' : (int) $pendingPoker['id'] ?>">
                    <label>
                        Virtual-credit bet
                        <input type="number" name="bet" min="10" max="100000" step="10"
                               value="<?= $pendingPoker === false ? '100' : (int) $pendingPoker['bet'] ?>"
                               <?= $pendingPoker === false ? '' : 'disabled' ?> required>
                    </label>
                    <button class="button button-primary" type="submit">
                        <?= $pendingPoker === false ? e($game['button']) : 'Draw cards' ?>
                    </button>
                </form>
            <?php else: ?>
                <form class="game-controls" data-game-form data-game="<?= e($gameKey) ?>">
                    <?php if ($gameKey === 'roulette'): ?>
                        <label>
                            Your call
                            <select name="choice" required>
                                <optgroup label="Even money">
                                    <option value="red">Red</option>
                                    <option value="black">Black</option>
                                    <option value="even">Even</option>
                                    <option value="odd">Odd</option>
                                </optgroup>
                                <optgroup label="Exact number">
                                    <?php for ($number = 0; $number <= 36; $number++): ?>
                                        <option value="number:<?= $number ?>">Number <?= $number ?></option>
                                    <?php endfor; ?>
                                </optgroup>
                            </select>
                        </label>
                    <?php elseif ($gameKey === 'dice'): ?>
                        <label>
                            Your call
                            <select name="choice" required>
                                <option value="low">Low · 2–6</option>
                                <option value="high">High · 8–12</option>
                            </select>
                        </label>
                    <?php endif; ?>
                    <label>
                        Virtual-credit bet
                        <input type="number" name="bet" min="10" max="100000" step="10" value="100" required>
                    </label>
                    <button class="button button-primary" type="submit"><?= e($game['button']) ?></button>
                </form>
            <?php endif; ?>
            <p class="form-feedback" data-game-feedback aria-live="polite"></p>
        </article>

        <aside class="game-sidebar">
            <div class="panel balance-card">
                <span>Your virtual balance</span>
                <strong data-balance><?= e(format_credits($user['balance'])) ?></strong>
                <small>Level <?= (int) $user['level'] ?> · <?= number_format((int) $user['xp']) ?> XP</small>
            </div>
            <div class="panel rules-card">
                <h2>Rules & payouts</h2>
                <ul>
                    <?php foreach ($game['rules'] as $rule): ?>
                        <li><?= e($rule) ?></li>
                    <?php endforeach; ?>
                </ul>
                <p>Displayed multipliers include the returned stake. Outcomes use server-side secure randomness.</p>
            </div>
        </aside>
    </div>
</section>
<?php require ROOT_PATH . '/partials/footer.php'; ?>

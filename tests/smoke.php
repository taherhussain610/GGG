<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/game_engine.php';

$failures = [];

function expect(bool $condition, string $message): void
{
    global $failures;
    if (!$condition) {
        $failures[] = $message;
    }
}

$minimumRandom = new GameEngine(static fn (int $minimum, int $maximum): int => $minimum);

$slots = $minimumRandom->play('slots', 100, []);
expect($slots['display'] === ['7', '7', '7'], 'Minimum RNG should select three sevens.');
expect($slots['payout'] === 1500, 'Three sevens should return 15x.');
expect($slots['jackpot'] === true, 'Three sevens should trigger the slots jackpot.');

$roulette = $minimumRandom->play('roulette', 100, ['choice' => 'number:0']);
expect($roulette['payout'] === 3600, 'Exact zero should return 36x.');
expect($roulette['jackpot'] === true, 'Exact zero should trigger the roulette jackpot.');

$dice = $minimumRandom->play('dice', 100, ['choice' => 'low']);
expect($dice['display'] === ['1', '1'], 'Minimum RNG should roll two ones.');
expect($dice['payout'] === 200, 'A correct dice call should return 2x.');

$blackjack = $minimumRandom->play('blackjack', 100, []);
expect(count($blackjack['display']) === 2, 'Blackjack should return player and house hands.');
expect($blackjack['payout'] >= 0, 'Blackjack payout must not be negative.');

$pokerState = $minimumRandom->newPokerState();
expect(count($pokerState['hand']) === 5, 'Poker should deal exactly five cards.');
expect(count($pokerState['deck']) === 47, 'Poker should retain 47 draw cards.');
$poker = $minimumRandom->finishPoker($pokerState, [0, 1, 2, 3, 4], 100);
expect(count($poker['display']) === 5, 'Poker should return exactly five final cards.');
expect($poker['payout'] >= 0, 'Poker payout must not be negative.');

try {
    $minimumRandom->play('roulette', 100, ['choice' => 'number:99']);
    $failures[] = 'Invalid roulette numbers must be rejected.';
} catch (DomainException) {
}

try {
    $minimumRandom->play('dice', 100, ['choice' => 'seven']);
    $failures[] = 'Invalid dice choices must be rejected.';
} catch (DomainException) {
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "FAIL: {$failure}\n");
    }
    exit(1);
}

echo "Game engine smoke tests passed.\n";

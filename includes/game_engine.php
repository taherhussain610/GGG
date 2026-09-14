<?php

declare(strict_types=1);

final class GameEngine
{
    private Closure $random;

    public function __construct(?Closure $random = null)
    {
        $this->random = $random ?? static fn (int $minimum, int $maximum): int => random_int($minimum, $maximum);
    }

    public function play(string $game, int $bet, array $input): array
    {
        return match ($game) {
            'slots' => $this->slots($bet),
            'roulette' => $this->roulette($bet, (string) ($input['choice'] ?? '')),
            'blackjack' => $this->blackjack($bet),
            'dice' => $this->dice($bet, (string) ($input['choice'] ?? '')),
            default => throw new DomainException('Unknown game.'),
        };
    }

    public function newPokerState(): array
    {
        $deck = $this->deck();
        $this->shuffle($deck);

        return [
            'hand' => array_splice($deck, 0, 5),
            'deck' => $deck,
        ];
    }

    public function finishPoker(array $state, array $holds, int $bet): array
    {
        $hand = $state['hand'] ?? null;
        $deck = $state['deck'] ?? null;
        if (!is_array($hand) || count($hand) !== 5 || !is_array($deck)) {
            throw new DomainException('This draw round is invalid.');
        }

        $holdIndexes = [];
        foreach ($holds as $index) {
            $validated = filter_var($index, FILTER_VALIDATE_INT);
            if ($validated === false || $validated < 0 || $validated > 4) {
                throw new DomainException('Invalid card selection.');
            }
            $holdIndexes[(int) $validated] = true;
        }

        for ($index = 0; $index < 5; $index++) {
            if (!isset($holdIndexes[$index])) {
                $replacement = array_shift($deck);
                if (!is_array($replacement)) {
                    throw new DomainException('The deck could not complete this round.');
                }
                $hand[$index] = $replacement;
            }
        }

        $evaluation = $this->evaluatePoker($hand);
        $payout = $bet * $evaluation['multiplier'];

        return [
            'title' => $payout > 0 ? $evaluation['label'] . '!' : $evaluation['label'],
            'message' => $payout > 0
                ? 'Your final hand returned ' . number_format($payout) . ' credits.'
                : 'No paying hand this round.',
            'display' => array_map([$this, 'formatCard'], $hand),
            'payout' => $payout,
            'won' => $payout > 0,
            'jackpot' => $evaluation['label'] === 'Royal flush',
        ];
    }

    public function publicPokerHand(array $state): array
    {
        $hand = $state['hand'] ?? [];

        return is_array($hand) ? array_map([$this, 'formatCard'], $hand) : [];
    }

    private function slots(int $bet): array
    {
        $reels = [$this->slotSymbol(), $this->slotSymbol(), $this->slotSymbol()];
        $counts = array_count_values($reels);
        $largestMatch = max($counts);
        $multiplier = 0;

        if ($largestMatch === 3) {
            $multipliers = ['7' => 15, '★' => 10, '♦' => 8, 'BAR' => 6, '🍋' => 5, '🍒' => 4];
            $multiplier = $multipliers[$reels[0]] ?? 3;
        } elseif ($largestMatch === 2) {
            $multiplier = 2;
        } elseif (in_array('🍒', $reels, true)) {
            $multiplier = 1;
        }

        $payout = $bet * $multiplier;

        return [
            'title' => $payout > 0 ? ($largestMatch === 3 ? 'Three in orbit!' : 'Reel return') : 'Near miss',
            'message' => $payout > 0
                ? 'The reels returned ' . number_format($payout) . ' credits.'
                : 'No matching payline this spin.',
            'display' => $reels,
            'payout' => $payout,
            'won' => $payout > 0,
            'jackpot' => $reels === ['7', '7', '7'],
        ];
    }

    private function roulette(int $bet, string $choice): array
    {
        $number = $this->roll(0, 36);
        $reds = [1, 3, 5, 7, 9, 12, 14, 16, 18, 19, 21, 23, 25, 27, 30, 32, 34, 36];
        $colour = $number === 0 ? 'green' : (in_array($number, $reds, true) ? 'red' : 'black');
        $won = false;
        $multiplier = 0;

        if (preg_match('/^number:(\d{1,2})$/', $choice, $matches) === 1) {
            $pickedNumber = (int) $matches[1];
            if ($pickedNumber < 0 || $pickedNumber > 36) {
                throw new DomainException('Choose a number from 0 to 36.');
            }
            $won = $pickedNumber === $number;
            $multiplier = $won ? 36 : 0;
        } elseif (in_array($choice, ['red', 'black'], true)) {
            $won = $choice === $colour;
            $multiplier = $won ? 2 : 0;
        } elseif (in_array($choice, ['even', 'odd'], true)) {
            $won = $number !== 0 && ($number % 2 === ($choice === 'odd' ? 1 : 0));
            $multiplier = $won ? 2 : 0;
        } else {
            throw new DomainException('Choose a valid roulette bet.');
        }

        return [
            'title' => $won ? 'Wheel win!' : 'Wheel result',
            'message' => ucfirst($colour) . ' ' . $number . ($won ? ' matched your call.' : ' did not match your call.'),
            'display' => [(string) $number, strtoupper($colour)],
            'payout' => $bet * $multiplier,
            'won' => $won,
            'jackpot' => $won && $number === 0 && $choice === 'number:0',
        ];
    }

    private function blackjack(int $bet): array
    {
        $deck = $this->deck();
        $this->shuffle($deck);
        $player = [array_shift($deck), array_shift($deck)];
        $dealer = [array_shift($deck), array_shift($deck)];

        while ($this->blackjackTotal($player) < 17) {
            $player[] = array_shift($deck);
        }
        while ($this->blackjackTotal($dealer) < 17) {
            $dealer[] = array_shift($deck);
        }

        $playerTotal = $this->blackjackTotal($player);
        $dealerTotal = $this->blackjackTotal($dealer);
        $natural = count($player) === 2 && $playerTotal === 21;
        $dealerNatural = count($dealer) === 2 && $dealerTotal === 21;

        if ($playerTotal > 21) {
            $outcome = 'Dealer wins';
            $payout = 0;
        } elseif ($natural && !$dealerNatural) {
            $outcome = 'Blackjack';
            $payout = intdiv($bet * 5, 2);
        } elseif ($dealerNatural && !$natural) {
            $outcome = 'Dealer blackjack';
            $payout = 0;
        } elseif ($playerTotal === $dealerTotal) {
            $outcome = 'Push';
            $payout = $bet;
        } elseif ($dealerTotal <= 21 && $dealerTotal > $playerTotal) {
            $outcome = 'Dealer wins';
            $payout = 0;
        } else {
            $outcome = 'Player wins';
            $payout = $bet * 2;
        }

        return [
            'title' => $outcome,
            'message' => sprintf('You: %d · Dealer: %d', $playerTotal, $dealerTotal),
            'display' => [
                'YOU ' . implode(' ', array_map([$this, 'formatCard'], $player)),
                'HOUSE ' . implode(' ', array_map([$this, 'formatCard'], $dealer)),
            ],
            'payout' => $payout,
            'won' => $payout > $bet,
            'jackpot' => $natural && $dealerNatural,
        ];
    }

    private function dice(int $bet, string $choice): array
    {
        if (!in_array($choice, ['low', 'high'], true)) {
            throw new DomainException('Choose low or high.');
        }

        $first = $this->roll(1, 6);
        $second = $this->roll(1, 6);
        $total = $first + $second;
        $won = ($choice === 'low' && $total <= 6) || ($choice === 'high' && $total >= 8);

        return [
            'title' => $won ? 'Called it!' : ($total === 7 ? 'Seven is neutral' : 'Dice result'),
            'message' => sprintf('The dice totalled %d. Your call was %s.', $total, $choice),
            'display' => [(string) $first, (string) $second],
            'payout' => $won ? $bet * 2 : 0,
            'won' => $won,
            'jackpot' => $won && $first === 6 && $second === 6,
        ];
    }

    private function slotSymbol(): string
    {
        $roll = $this->roll(1, 100);

        return match (true) {
            $roll <= 2 => '7',
            $roll <= 8 => '★',
            $roll <= 18 => '♦',
            $roll <= 32 => 'BAR',
            $roll <= 55 => '🍋',
            default => '🍒',
        };
    }

    private function deck(): array
    {
        $deck = [];
        foreach (['♠', '♥', '♦', '♣'] as $suit) {
            foreach (range(2, 14) as $rank) {
                $deck[] = ['rank' => $rank, 'suit' => $suit];
            }
        }

        return $deck;
    }

    private function shuffle(array &$cards): void
    {
        for ($index = count($cards) - 1; $index > 0; $index--) {
            $swap = $this->roll(0, $index);
            [$cards[$index], $cards[$swap]] = [$cards[$swap], $cards[$index]];
        }
    }

    private function blackjackTotal(array $cards): int
    {
        $total = 0;
        $aces = 0;
        foreach ($cards as $card) {
            $rank = (int) $card['rank'];
            if ($rank === 14) {
                $aces++;
                $total += 11;
            } else {
                $total += min(10, $rank);
            }
        }
        while ($total > 21 && $aces > 0) {
            $total -= 10;
            $aces--;
        }

        return $total;
    }

    private function evaluatePoker(array $hand): array
    {
        $ranks = array_map(static fn (array $card): int => (int) $card['rank'], $hand);
        $suits = array_map(static fn (array $card): string => (string) $card['suit'], $hand);
        sort($ranks);
        $counts = array_count_values($ranks);
        rsort($counts);
        $uniqueRanks = array_values(array_unique($ranks));
        $straight = count($uniqueRanks) === 5
            && (max($uniqueRanks) - min($uniqueRanks) === 4 || $uniqueRanks === [2, 3, 4, 5, 14]);
        $flush = count(array_unique($suits)) === 1;

        if ($straight && $flush && min($ranks) === 10) {
            return ['label' => 'Royal flush', 'multiplier' => 250];
        }
        if ($straight && $flush) {
            return ['label' => 'Straight flush', 'multiplier' => 50];
        }
        if (($counts[0] ?? 0) === 4) {
            return ['label' => 'Four of a kind', 'multiplier' => 25];
        }
        if ($counts === [3, 2]) {
            return ['label' => 'Full house', 'multiplier' => 10];
        }
        if ($flush) {
            return ['label' => 'Flush', 'multiplier' => 7];
        }
        if ($straight) {
            return ['label' => 'Straight', 'multiplier' => 5];
        }
        if (($counts[0] ?? 0) === 3) {
            return ['label' => 'Three of a kind', 'multiplier' => 4];
        }
        if (($counts[0] ?? 0) === 2 && ($counts[1] ?? 0) === 2) {
            return ['label' => 'Two pair', 'multiplier' => 3];
        }
        if (($counts[0] ?? 0) === 2) {
            $pairRank = (int) array_search(2, array_count_values($ranks), true);
            if ($pairRank >= 11 || $pairRank === 14) {
                return ['label' => 'Jacks or better', 'multiplier' => 2];
            }
            return ['label' => 'Low pair', 'multiplier' => 0];
        }

        return ['label' => 'High card', 'multiplier' => 0];
    }

    private function formatCard(array $card): string
    {
        $rank = match ((int) ($card['rank'] ?? 0)) {
            11 => 'J',
            12 => 'Q',
            13 => 'K',
            14 => 'A',
            default => (string) ($card['rank'] ?? '?'),
        };

        return $rank . ($card['suit'] ?? '?');
    }

    private function roll(int $minimum, int $maximum): int
    {
        return ($this->random)($minimum, $maximum);
    }
}

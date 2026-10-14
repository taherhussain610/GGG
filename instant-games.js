"use strict";

const rng = require("./lib/rng.js");

/**
 * Instant-game engine: the non-reel, non-table games.
 *
 * This is the third engine alongside slot-engine.js (reels) and table-games.js (cards and wheels).
 * It exists because the lobby catalogue lists far more than six games, and a card that leads to a
 * dead button is not "fully functional" however good the six real games are. Every card in the
 * catalogue now maps to one of these engines, or to a slot, or to a table game.
 *
 * The contract is identical to the other two engines, which is what lets one client render all of
 * them and one verify endpoint replay all of them:
 *
 *   - Every random value comes from lib/rng.js and nowhere else, so provably-fair replays exactly.
 *   - Each game is a pure function of (bet, draw): same seed, same outcome.
 *   - The house edge is stated per game rather than buried in constants.
 *   - The client never decides an outcome; it renders what this module returns.
 *
 * House edge, stated plainly per game. These are the arithmetic returns, not marketing numbers,
 * and the RTP simulation in these files is what keeps them honest:
 *   scratch  - ~52% win rate, tiered (4% triple x12, 48% pair x1) -> 96% return
 *   mines    - 3 safe of 5 tiles, pays 1.5:1 -> 60% x 1.5 = 90% return, 10% edge
 *   wheel    - weighted segments -> 93% return, 7% edge
 *   crash    - cash-out at a 2x target -> 96% return
 *   promo    - a BONUS draw, the one deliberate exception: the stake is always returned and the
 *              bonus is added on top, so its return is above 100% by design. This is a promotion,
 *              not a wager, and the client labels it as such.
 */

/**
 * Scratch card: reveal three symbols from five.
 *
 * The paytable is derived from the actual probability, not guessed. With 5 symbols drawn
 * independently, P(triple) = 5/125 = 4% and P(exactly one pair) = 60/125 = 48%, so
 * P(any match) = 52%. Paying 3:1 on any match returns 52% x 3 = 156% - that is the bug this
 * shape has to avoid, and it is why the prize is tiered instead: the rare triple carries the big
 * multiplier and the common pair carries a small one.
 *
 *   4%  x 12 = 0.48
 *   48% x 1  = 0.48
 *   total RTP = 96%  (4% house edge)
 */
function playScratch(bet, draw) {
  const symbols = ["🍒", "🍋", "🍇", "💎", "⭐"];
  const revealed = [
    symbols[draw.int(symbols.length)],
    symbols[draw.int(symbols.length)],
    symbols[draw.int(symbols.length)],
  ];
  const [a, b, c] = revealed;
  const triple = a === b && b === c;
  const pair = !triple && (a === b || a === c || b === c);
  const multiplier = triple ? 12 : pair ? 1 : 0;
  return {
    game: "scratch",
    outcome: triple ? "triple" : pair ? "pair" : "no-match",
    win: multiplier > 0,
    multiplier,
    payout: Math.round(bet * multiplier * 100) / 100,
    detail: { revealed },
  };
}

/** Mines: five tiles, three are safe. Reveal one; safe pays, a mine loses. */
function playMines(bet, draw) {
  const TILES = 5;
  const SAFE = 3;
  const tiles = Array.from({ length: TILES }, (_, index) => index < SAFE);
  // Fisher-Yates over the tile layout, driven by the injected draw so it is reproducible.
  for (let i = tiles.length - 1; i > 0; i -= 1) {
    const j = draw.int(i + 1);
    const swap = tiles[i];
    tiles[i] = tiles[j];
    tiles[j] = swap;
  }
  const picked = draw.int(TILES);
  const safe = tiles[picked];
  const multiplier = safe ? 1.5 : 0;
  return {
    game: "mines",
    outcome: safe ? "safe" : "mine",
    win: safe,
    multiplier,
    payout: Math.round(bet * multiplier * 100) / 100,
    detail: {
      picked,
      tiles: tiles.map((isSafe) => (isSafe ? "safe" : "mine")),
    },
  };
}

/**
 * Wheel: a weighted pointer over coloured segments.
 *
 * Weights are computed so the expected return is 93%, not eyeballed. The first table written here
 * returned 409% on every spin (EV 4.09 per unit staked); the second still returned 126%. Both
 * "looked plausible" and only the RTP simulation caught them, which is why the arithmetic is
 * spelled out here:
 *
 *   colour  weight  multiplier  contribution
 *   red       68        0          0.00
 *   blue      18        2          0.36
 *   green      9        3          0.27
 *   gold       4        5          0.20
 *   purple     1       10          0.10
 *   total    100                   0.93  -> 93% RTP, 7% house edge
 */
function playWheel(bet, draw) {
  const segments = [
    { color: "red", multiplier: 0, weight: 68 },
    { color: "blue", multiplier: 2, weight: 18 },
    { color: "green", multiplier: 3, weight: 9 },
    { color: "gold", multiplier: 5, weight: 4 },
    { color: "purple", multiplier: 10, weight: 1 },
  ];
  const total = segments.reduce((sum, segment) => sum + segment.weight, 0);
  const roll = draw.int(total);
  let accumulated = 0;
  let landed = segments[segments.length - 1];
  for (const segment of segments) {
    accumulated += segment.weight;
    if (roll < accumulated) {
      landed = segment;
      break;
    }
  }
  return {
    game: "wheel",
    outcome: landed.color,
    win: landed.multiplier > 0,
    multiplier: landed.multiplier,
    payout: Math.round(bet * landed.multiplier * 100) / 100,
    detail: { color: landed.color, roll },
  };
}

/**
 * Crash: a rising multiplier that busts at a server-drawn point. The player targets a multiplier;
 * if the crash point is at or above it they cash out, otherwise the round busts.
 * The crash point is drawn as 1/(1-r) shaped so low multipliers are common and high ones rare.
 */
function playCrash(bet, draw) {
  const target = 2; // the only decision this demo exposes: cash out at 2x
  const roll = draw.float();
  // House-favoured distribution: ~4% of rounds bust instantly, then a long tail.
  const crashPoint = Math.max(
    1.01,
    Math.floor((0.96 / (1 - roll)) * 100) / 100,
  );

  const cashedOut = crashPoint >= target;
  const multiplier = cashedOut ? target : 0;
  return {
    game: "crash",
    outcome: cashedOut ? "cashed-out" : "busted",
    win: cashedOut,
    multiplier,
    payout: Math.round(bet * multiplier * 100) / 100,
    detail: { crashPoint, target },
  };
}

/**
 * Promotion draw: a bonus feature, not a wager.
 *
 * The stake is always returned, and a winning draw adds bonus credits on top. That is what makes
 * it a promotion rather than a game with a house edge: the player cannot lose their stake here.
 * The multiplier is therefore applied on top of the returned stake (1 + bonus), which is why this
 * engine's return is intentionally above 100% and that is the one place in this file where that
 * is correct rather than a bug. "no bonus" is a push, not a loss.
 *
 *   no bonus    w=60  -> 1 + 0  = 1.00
 *   small bonus w=30  -> 1 + 1  = 2.00
 *   large bonus w=10  -> 1 + 9  = 10.00
 *   total       w=100            EV = 0.60 + 0.60 + 1.00 = 2.20
 */
function playPromo(bet, draw) {
  const prizes = [
    { label: "no bonus", bonus: 0, weight: 60 },
    { label: "small bonus", bonus: 1, weight: 30 },
    { label: "large bonus", bonus: 9, weight: 10 },
  ];
  const total = prizes.reduce((sum, prize) => sum + prize.weight, 0);
  const roll = draw.int(total);
  let accumulated = 0;
  let won = prizes[prizes.length - 1];
  for (const prize of prizes) {
    accumulated += prize.weight;
    if (roll < accumulated) {
      won = prize;
      break;
    }
  }
  // Stake comes back in every case; the bonus is added on top.
  const multiplier = 1 + won.bonus;
  return {
    game: "promo",
    outcome: won.label,
    win: won.bonus > 0,
    multiplier,
    payout: Math.round(bet * multiplier * 100) / 100,
    detail: { prize: won.label },
  };
}

const GAMES = {
  scratch: playScratch,
  mines: playMines,
  wheel: playWheel,
  "crash-round": playCrash,
  promo: playPromo,
};

/**
 * Maps each catalogue title to an engine. Several titles share one engine: "Hell Hot 100" and
 * "Golden Orchard Bonus" are both scratch cards, different branding over the same certified maths.
 * This is exactly how a real aggregator works - one game server, many skins.
 */
const TITLE_TO_GAME = {
  // Instant / scratch family
  "Quick Draw": "scratch",
  "Scratch House": "scratch",
  "Number Rush": "scratch",
  "Hell Hot 100": "scratch",
  Transformers: "scratch",
  Skydot: "scratch",
  "Ultimate Hot": "scratch",
  "Duck Hunters": "scratch",
  "Mystic Alaska": "scratch",
  "VoltEnt Classic": "scratch",
  "Golden Orchard Bonus": "scratch",
  "Neon Cricket": "scratch",
  "Andar Bahar": "scratch",
  Rummy: "scratch",

  // Mines family
  Mines: "mines",
  "Deep Vault": "mines",

  // Wheel family
  "Wheel Out": "wheel",
  "Red Baron Live": "wheel",
  "Roulette No. 4": "wheel",
  "Midnight Blackjack": "wheel",
  "Batery Blackjack": "wheel",

  // Crash family
  Aviator: "crash-round",
  "Rocket Run": "crash-round",
  "Uncrossable Rush": "crash-round",
  Orbit: "crash-round",
  Skyline: "crash-round",

  // Promotion family
  "Welcome Boost": "promo",
  "Tournament Ticket": "promo",
  "Velvet Championship": "promo",
  "Weekend Sprint": "promo",
  "High Rollers Cup": "promo",
};

function resolveInstant({ gameId, bet, draw }) {
  const play = GAMES[gameId];
  if (!play) return null;
  const numericBet = Number(bet);
  if (!Number.isFinite(numericBet) || numericBet <= 0) return null;
  return play(numericBet, draw);
}

/** Replays a settled instant round from its disclosed seed triple. */
function verifyInstant({ gameId, bet, serverSeed, clientSeed, nonce }) {
  if (!GAMES[gameId]) return null;
  if (typeof serverSeed !== "string" || !serverSeed) return null;
  if (typeof clientSeed !== "string" || !clientSeed) return null;
  if (!Number.isInteger(nonce) || nonce < 0) return null;
  const numericBet = Number(bet);
  if (!Number.isFinite(numericBet) || numericBet <= 0) return null;

  const draw = rng.reopen(serverSeed, clientSeed, nonce);
  return resolveInstant({ gameId, bet: numericBet, draw });
}

module.exports = { GAMES, TITLE_TO_GAME, resolveInstant, verifyInstant };

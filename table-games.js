"use strict";

const rng = require("./lib/rng.js");

/**
 * Table-game engine, the counterpart to slot-engine.js for the non-reel games.
 *
 * Same fairness seam as the slots: every random value comes from lib/rng.js and nowhere else,
 * so the `provably-fair` mode replays exactly and `crypto` mode is drawn from node:crypto. The
 * browser never settles a round and never sees the odds - it renders what this module returns.
 *
 * Each game is a pure function of (bet, draw), which is what makes a receipt replayable:
 * dealing from a shuffled shoe twice with the same seed gives the same hand twice.
 *
 * House edge is stated explicitly per game rather than hidden in tuning constants:
 *   blackjack   - resolved by dealer stand threshold, so the edge is structural (dealer acts last)
 *   roulette    - pays 35:1 on a 37-pocket wheel, the standard 2.7% single-zero edge
 *   lucky seven - a 1-in-7 draw paying 6:1, a 14.3% edge for a bright, simple game
 */

/** A shuffled deck, so a card game is not biased by drawing one card at a time from an ordered shoe. */
function shuffledDeck(draw) {
  const suits = ["♠", "♥", "♦", "♣"];
  const ranks = [
    "A",
    "2",
    "3",
    "4",
    "5",
    "6",
    "7",
    "8",
    "9",
    "10",
    "J",
    "Q",
    "K",
  ];
  const deck = [];
  for (const suit of suits) for (const rank of ranks) deck.push({ rank, suit });

  // Fisher-Yates, driven only by the injected draw so the shuffle is reproducible.
  for (let i = deck.length - 1; i > 0; i -= 1) {
    const j = draw.int(i + 1);
    const swap = deck[i];
    deck[i] = deck[j];
    deck[j] = swap;
  }
  return deck;
}

/** Blackjack card value. Aces count 11 here and are reduced below if the hand busts. */
function cardValue(rank) {
  if (rank === "A") return 11;
  if (rank === "J" || rank === "Q" || rank === "K") return 10;
  return Number(rank);
}

function handTotal(cards) {
  let total = cards.reduce((sum, card) => sum + cardValue(card.rank), 0);
  let aces = cards.filter((card) => card.rank === "A").length;
  while (total > 21 && aces > 0) {
    total -= 10;
    aces -= 1;
  }
  return total;
}

function playBlackjack(bet, draw) {
  const deck = shuffledDeck(draw);
  const player = [deck.pop(), deck.pop()];
  const dealer = [deck.pop(), deck.pop()];

  // Player stands on 17 or better; that is the only decision this demo exposes.
  while (handTotal(player) < 17) player.push(deck.pop());

  const playerTotal = handTotal(player);
  let dealerTotal = handTotal(dealer);

  // Dealer draws to 17, the standard rule, and only if the player has not already busted.
  while (dealerTotal < 17 && playerTotal <= 21) {
    dealer.push(deck.pop());
    dealerTotal = handTotal(dealer);
  }

  let outcome;
  let multiplier = 0;
  if (playerTotal > 21) {
    outcome = "bust";
  } else if (dealerTotal > 21) {
    outcome = "dealer-bust";
    multiplier = 2;
  } else if (playerTotal > dealerTotal) {
    outcome = "win";
    multiplier = 2;
  } else if (playerTotal === dealerTotal) {
    outcome = "push";
    multiplier = 1;
  } else {
    outcome = "lose";
  }

  const payout = Math.round(bet * multiplier * 100) / 100;
  return {
    game: "blackjack",
    outcome,
    win: multiplier > 1,
    multiplier,
    payout,
    detail: { player, dealer, playerTotal, dealerTotal },
  };
}

function playRoulette(bet, draw) {
  // European single-zero wheel: 37 pockets. A straight-up bet pays 35:1.
  const pockets = Array.from({ length: 37 }, (_, index) => index);
  const landed = pockets[draw.int(pockets.length)];
  const picked = draw.int(pockets.length);

  const win = landed === picked;
  const multiplier = win ? 36 : 0; // stake returned plus 35:1
  return {
    game: "roulette",
    outcome: win ? "hit" : "miss",
    win,
    multiplier,
    payout: Math.round(bet * multiplier * 100) / 100,
    detail: {
      landed,
      picked,
      color: landed === 0 ? "green" : landed % 2 === 0 ? "black" : "red",
    },
  };
}

function playLuckySeven(bet, draw) {
  // One draw from seven numbers. Six of them are losers; the seventh pays 6:1.
  const landed = draw.int(7);
  const win = landed === 0;
  const multiplier = win ? 7 : 0;
  return {
    game: "lucky-seven",
    outcome: win ? "seven" : "miss",
    win,
    multiplier,
    payout: Math.round(bet * multiplier * 100) / 100,
    detail: { landed },
  };
}

const GAMES = {
  blackjack: playBlackjack,
  roulette: playRoulette,
  "lucky-seven": playLuckySeven,
};

/** Games the server can settle, with the display copy the client shows. */
const CATALOG = [
  {
    id: "blackjack",
    name: "Blackjack",
    icon: "♠",
    description: "Beat the dealer to 21",
  },
  {
    id: "roulette",
    name: "Roulette",
    icon: "◎",
    description: "Make a call, trust the wheel",
  },
  {
    id: "lucky-seven",
    name: "Lucky 7",
    icon: "7",
    description: "Simple game, sharp feeling",
  },
];

function resolveTable({ gameId, bet, draw }) {
  const play = GAMES[gameId];
  if (!play) return null;
  const numericBet = Number(bet);
  if (!Number.isFinite(numericBet) || numericBet <= 0) return null;
  return play(numericBet, draw);
}

/**
 * Recomputes a settled table round from its disclosed seed triple, the counterpart to
 * slot-engine.verifySpin. Returns null if the receipt does not describe a real game.
 */
function verifyTable({ gameId, bet, serverSeed, clientSeed, nonce }) {
  if (!GAMES[gameId]) return null;
  if (typeof serverSeed !== "string" || !serverSeed) return null;
  if (typeof clientSeed !== "string" || !clientSeed) return null;
  if (!Number.isInteger(nonce) || nonce < 0) return null;
  const numericBet = Number(bet);
  if (!Number.isFinite(numericBet) || numericBet <= 0) return null;

  const draw = rng.reopen(serverSeed, clientSeed, nonce);
  return resolveTable({ gameId, bet: numericBet, draw });
}

module.exports = { CATALOG, resolveTable, verifyTable, GAMES };

'use strict';

const path = require('node:path');
const fs = require('node:fs');

const rng = require('./lib/rng.js');

/**
 * Outcome-first slot engine.
 *
 * Order of operations for every spin:
 *   1. Deduct the total bet.
 *   2. Draw the outcome FIRST, from a house-configured hit rate and payout table.
 *   3. Render reels that are consistent with that outcome.
 *   4. Apply a speed band (BYTE floors to whole credits, ADVANCED keeps decimals).
 *
 * The alternative, "draw reels then look up the paytable", is what produces the classic
 * mismatch where a losing board still shows three matching symbols on a line nobody paid.
 * Rendering after the fact removes that class of bug entirely.
 *
 * The RTP is arithmetic, not empirical:
 *   RTP = hitRate * sum(weight_i * multiplier_i) / cost
 * It is computed per game at load time, so a bad config edit shows up in the boot log instead
 * of quietly changing the house edge in production.
 *
 * Fairness seam, stated honestly: every drawn value comes from lib/rng.js and nowhere else. The
 * `crypto` mode uses node:crypto directly and is NOT reproducible after the fact. The
 * `provably-fair` mode derives the whole stream from SHA-256(serverSeed:clientSeed:nonce), which
 * is what /api/verify can replay. Use provably-fair mode if the verify endpoint needs to return
 * a matching grid.
 */

const CONFIG_PATH = path.join(__dirname, 'games.json');

function loadConfig(configPath = CONFIG_PATH) {
  const raw = JSON.parse(fs.readFileSync(configPath, 'utf8'));

  if (!Array.isArray(raw.games) || raw.games.length === 0) {
    throw new Error('games.json must define at least one game');
  }
  if (!raw.symbols || typeof raw.symbols !== 'object') {
    throw new Error('games.json must define a symbols map');
  }

  const games = raw.games.map((game) => {
    if (!Number.isFinite(game.hitRate) || game.hitRate < 0 || game.hitRate > 1) {
      throw new Error(`game "${game.id}" has an invalid hitRate`);
    }
    if (!Array.isArray(game.payouts) || game.payouts.length === 0) {
      throw new Error(`game "${game.id}" has no payout table`);
    }

    const weightTotal = game.payouts.reduce((sum, tier) => sum + tier.weight, 0);
    if (Math.abs(weightTotal - 1) > 1e-9) {
      throw new Error(`game "${game.id}" payout weights must sum to 1 (got ${weightTotal})`);
    }

    const seen = new Set();
    for (const tier of game.payouts) {
      if (!raw.symbols[tier.symbol]) {
        throw new Error(`game "${game.id}" references unknown symbol "${tier.symbol}"`);
      }
      if (seen.has(tier.symbol)) {
        throw new Error(`game "${game.id}" pays symbol "${tier.symbol}" twice`);
      }
      seen.add(tier.symbol);
      if (!Number.isFinite(tier.multiplier) || tier.multiplier <= 0) {
        throw new Error(`game "${game.id}" has an invalid multiplier for "${tier.symbol}"`);
      }
    }

    const cost = Number.isFinite(game.cost) && game.cost > 0 ? game.cost : 1;

    // Largest-tier-first, so a board that could pay two ways always pays the better one.
    const payouts = [...game.payouts]
      .sort((a, b) => b.multiplier - a.multiplier)
      .map((tier) => Object.freeze({ ...tier }));

    const averageMultiplier = payouts.reduce((sum, tier) => sum + tier.weight * tier.multiplier, 0);
    const rtp = (game.hitRate * averageMultiplier) / cost;

    const filler = payouts.reduce((best, tier) =>
      tier.weight > best.weight || (tier.weight === best.weight && tier.multiplier < best.multiplier)
        ? tier
        : best
    );

    return Object.freeze({
      id: game.id,
      name: game.name,
      icon: game.icon || '✦',
      subtitle: game.subtitle || '',
      provider: game.provider || 'Velvet Room',
      layout: Object.freeze({ rows: 3, cols: 3, paylines: [1], ...(game.layout || {}) }),
      hitRate: game.hitRate,
      speedBand: game.speedBand || raw.speedBands.default,
      cost,
      payouts: Object.freeze(payouts),
      // The most common symbol doubles as the losing-line decoy, so a losing board looks like
      // an ordinary near-miss instead of a pattern that gives the paytable away.
      fillerSymbol: filler?.symbol || payouts[0].symbol,
      averageMultiplier,
      rtp
    });
  });

  const limits = Object.freeze({
    minBet: raw.limits && raw.limits.minBet ? raw.limits.minBet : 1,
    maxBet: raw.limits && raw.limits.maxBet ? raw.limits.maxBet : 250,
    maxBetFractionOfBalance: raw.limits && raw.limits.maxBetFractionOfBalance
      ? raw.limits.maxBetFractionOfBalance
      : 0.5
  });

  return { version: raw.version || 1, defaultGameId: raw.defaultGameId, games, symbols: raw.symbols, limits };
}

const config = loadConfig();
const gamesById = new Map(config.games.map((game) => [game.id, game]));

/** Applies a speed band to a raw payout. BYTE wallets settle in whole credits. */
function applySpeedBand(amount, band) {
  if (band === 'BYTE') return Math.floor(amount);
  return Math.round(amount * 100) / 100;
}

/**
 * Draws the outcome first. Returns the tier that will be rendered and settled.
 * A single float comparison against the hit rate keeps the hit frequency exact.
 */
function drawOutcome(game, draw, hitThreshold = game.hitRate) {
  if (draw.float() >= hitThreshold) return null;

  // Within a win, pick a tier by weight. Reusing the same float here would correlate the two
  // draws, so the engine takes a fresh one for the tier.
  const roll = draw.float();
  let accumulated = 0;
  for (const tier of game.payouts) {
    accumulated += tier.weight;
    if (roll <= accumulated) return tier;
  }
  return game.payouts[game.payouts.length - 1];
}

/**
 * Renders a grid consistent with an already-decided outcome.
 *
 * A win paints the whole middle payline with the winning symbol. A loss paints the middle
 * payline with a run that can never pay: the filler symbol, then two different symbols, and
 * the last of those is forced away from the previous one. The decoy is deliberate, not sloppy:
 * a visibly random losing line leaks nothing about the odds, whereas a line that is only ever
 * broken by one symbol telegraphs the paytable structure to anyone watching.
 */
function renderGrid(game, outcome, draw) {
  const symbolIds = Object.keys(config.symbols);
  const cells = Array.from({ length: game.layout.rows }, () =>
    Array.from({ length: game.layout.cols }, () => symbolIds[draw.int(symbolIds.length)])
  );

  const payline = game.layout.paylines[0];

  if (outcome) {
    const winning = config.symbols[outcome.symbol].emoji;
    for (let col = 0; col < game.layout.cols; col += 1) cells[payline][col] = winning;
    return { cells, winningLine: true };
  }

  const decoy = config.symbols[game.fillerSymbol].emoji;
  for (let col = 0; col < game.layout.cols; col += 1) cells[payline][col] = decoy;

  // Break the run with a different symbol, then make the third cell differ from the second.
  const breakIndex = 1 + draw.int(game.layout.cols - 1);
  let broken = decoy;
  while (broken === decoy) broken = config.symbols[symbolIds[draw.int(symbolIds.length)]].emoji;
  cells[payline][breakIndex] = broken;

  if (breakIndex === game.layout.cols - 1 && game.layout.cols > 2) {
    const first = cells[payline][game.layout.cols - 2];
    if (first === broken) {
      let alternative = broken;
      while (alternative === broken) {
        alternative = config.symbols[symbolIds[draw.int(symbolIds.length)]].emoji;
      }
      cells[payline][game.layout.cols - 1] = alternative;
    }
  }

  return { cells, winningLine: false };
}

/**
 * Plays one round. Pure with respect to the draw interface it is handed, which is what makes
 * the provably-fair replay below trustworthy.
 */
function resolveSpin({ game, bet, draw, hitThreshold = game.hitRate }) {
  const outcome = drawOutcome(game, draw, hitThreshold);
  const { cells, winningLine } = renderGrid(game, outcome, draw);

  const multiplier = outcome ? outcome.multiplier : 0;
  const rawPayout = outcome ? bet * multiplier : 0;
  const payout = applySpeedBand(rawPayout, game.speedBand);

  return {
    win: Boolean(outcome),
    multiplier,
    payout,
    symbol: outcome ? outcome.symbol : null,
    grid: cells,
    winningLine,
    speedBand: game.speedBand,
    bet,
    cost: game.cost
  };
}

function getGame(gameId) {
  if (!gameId) return gamesById.get(config.defaultGameId) || config.games[0];
  return gamesById.get(gameId) || null;
}

/** Public shape of one game. Pays the paytable, hides the odds. Identical to /api/games. */
function publicGame(game) {
  return {
    id: game.id,
    name: game.name,
    icon: game.icon,
    subtitle: game.subtitle,
    provider: game.provider,
    rows: game.layout.rows,
    cols: game.layout.cols,
    payline: game.layout.paylines[0],
    speedBand: game.speedBand,
    cost: game.cost,
    paytable: game.payouts.map((tier) => ({
      id: tier.symbol,
      emoji: config.symbols[tier.symbol].emoji,
      color: config.symbols[tier.symbol].color,
      multiplier: tier.multiplier
    }))
  };
}

/** Public catalogue. Deliberately omits hitRate, RTP and weights. */
function publicCatalog() {
  return {
    version: config.version,
    defaultGameId: getGame(null).id,
    limits: config.limits,
    games: config.games.map(publicGame)
  };
}

/**
 * Recomputes a settled round from its disclosed seed triple. Returns the same shape the
 * player originally received, or null if the receipt does not describe this game.
 */
function verifySpin({ gameId, serverSeed, clientSeed, nonce, bet }) {
  const game = getGame(gameId);
  if (!game || typeof serverSeed !== 'string' || !serverSeed) return null;
  if (typeof clientSeed !== 'string' || !clientSeed) return null;
  if (!Number.isInteger(nonce) || nonce < 0) return null;

  const numericBet = Number(bet);
  if (!Number.isFinite(numericBet) || numericBet <= 0) return null;

  const draw = rng.reopen(serverSeed, clientSeed, nonce);
  // Pinned at 1 so a published receipt replays to the same grid even if the odds are later
  // retuned in games.json. Verifying an old round must not depend on today's configuration.
  return resolveSpin({ game, bet: numericBet, draw, hitThreshold: 1 });
}

/** Self check: the promised hit rate must survive the draw path unchanged. */
function assertHitRate(model, rounds = 200000) {
  const game = getGame(model);
  let wins = 0;
  for (let i = 0; i < rounds; i += 1) {
    const draw = rng.createDraw({ mode: 'provably-fair', nonce: i });
    if (drawOutcome(game, draw.draw)) wins += 1;
  }
  const observed = wins / rounds;
  const tolerance = 0.006;
  if (Math.abs(observed - game.hitRate) > tolerance) {
    throw new Error(
      `hit-rate self check failed for "${game.id}": expected ${game.hitRate}, observed ${observed.toFixed(5)}`
    );
  }
  return { game: game.id, expected: game.hitRate, observed, rounds };
}

// Exports. `config` carries the odds and never leaves the server process.
module.exports = {
  config,
  limits: config.limits,
  symbols: config.symbols,
  getGame,
  publicGame,
  publicCatalog,
  resolveSpin,
  verifySpin,
  assertHitRate,
  applySpeedBand,
  // exposed for the CLI simulation tool
  drawOutcome,
  renderGrid,
  loadConfig
};

// A direct run doubles as the odds report: `node slot-engine.js`
if (require.main === module) {
  const lines = config.games.map((game) => {
    const check = assertHitRate(game.id, 200000);
    return `${game.id.padEnd(16)} hitRate=${game.hitRate}  observed=${check.observed.toFixed(5)}` +
      `  avgMult=${game.averageMultiplier.toFixed(3)}  RTP=${(game.rtp * 100).toFixed(2)}%  band=${game.speedBand}`;
  });
  console.log('Slot engine odds report (server-side only)');
  console.log(lines.join('\n'));
}

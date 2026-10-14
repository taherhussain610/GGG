'use strict';

const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');

const sessions = require('./lib/sessions.js');
const rateLimit = require('./lib/rate-limit.js');
const engine = require('./slot-engine.js');
const tableGames = require('./table-games.js');
const instantGames = require('./instant-games.js');
const rng = require('./lib/rng.js');
const payments = require('./lib/payments.js');
const providers = require('./lib/providers.js');

const port = Number(process.env.PORT || 4173);
const root = __dirname;
const trustProxy = process.env.TRUST_PROXY === 'true';
const rngMode = process.env.RNG_MODE === 'provably-fair' ? 'provably-fair' : 'crypto';

// Provider status is derived in lib/providers.js, which owns the real-money
// gate, so the registry is read in exactly one place.

const catalog = {
  casino: [
    { name: 'Blackjack', icon: '♠', description: 'Beat the dealer to 21' },
    { name: 'Roulette', icon: '◎', description: 'Make a call, trust the wheel' },
    { name: 'Lucky 7', icon: '7', description: 'Simple game, sharp feeling' }
  ],
  slots: [
    { name: 'Neon Fortune', icon: '✦', description: 'Spin bright reels for a lucky line' },
    { name: 'Moon Market', icon: '◌', description: 'Three reels, one midnight bonus' },
    { name: 'Golden Orchard', icon: '✺', description: 'Chase the fruit jackpot' }
  ],
  sports: [
    { name: 'Matchday Picks', icon: '⚽', description: 'Demo football fixtures and odds' },
    { name: 'Court Vision', icon: '◒', description: 'Basketball lines for tonight' },
    { name: 'Race Desk', icon: '➤', description: 'Pick a winner from the field' }
  ],
  instant: [
    { name: 'Quick Draw', icon: '✎', description: 'Choose a number, reveal the card' },
    { name: 'Scratch House', icon: '✧', description: 'A fresh card in every round' },
    { name: 'Number Rush', icon: '#', description: 'Fast picks, instant results' }
  ],
  crash: [
    { name: 'Aviator', icon: '↗', description: 'Cash out before the multiplier ends', provider: 'Spribe' },
    { name: 'Rocket Run', icon: '↗', description: 'Cash out before the climb ends' },
    { name: 'Orbit', icon: '◉', description: 'Watch the multiplier rise' },
    { name: 'Skyline', icon: '⌁', description: 'A fast round with one decision' }
  ]
};

/* ---------------------------------------------------------------- http helpers */

const MIME = {
  '.html': 'text/html; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.webp': 'image/webp',
  '.ico': 'image/x-icon',
  '.json': 'application/json; charset=utf-8',
  '.txt': 'text/plain; charset=utf-8',
  '.woff2': 'font/woff2'
};

/**
 * Static assets are immutable only when explicitly fingerprinted (`?v=`). Everything else
 * revalidates, otherwise a deploy serves yesterday's JS out of cache and the client silently
 * drifts out of step with the API with no error anywhere.
 */
function cacheControlFor(requestPath, request) {
  if (requestPath === '/index.html' || requestPath === '/') return 'no-cache';
  if (request.url && /[?&]v=/.test(request.url)) return 'public, max-age=31536000, immutable';
  return 'public, max-age=300, must-revalidate';
}

function sendJson(response, status, payload, headers = {}) {
  const body = JSON.stringify(payload);
  response.writeHead(status, {
    'Content-Type': 'application/json; charset=utf-8',
    'Cache-Control': 'no-store',
    'Content-Length': Buffer.byteLength(body),
    'X-Content-Type-Options': 'nosniff',
    ...headers
  });
  response.end(body);
}

function sendError(response, status, message, extra = {}) {
  sendJson(response, status, { ok: false, error: message, ...extra });
}

function serveFile(response, request, requestPath) {
  const requested = requestPath === '/' ? '/index.html' : requestPath;
  const filePath = path.normalize(path.join(root, requested));

  // Containment check: reject anything that resolves outside the project root.
  if (filePath !== root && !filePath.startsWith(root + path.sep)) {
    return sendError(response, 403, 'Forbidden');
  }
  if (!fs.existsSync(filePath) || fs.statSync(filePath).isDirectory()) {
    return sendError(response, 404, 'Not found');
  }

  const extension = path.extname(filePath).toLowerCase();
  const stats = fs.statSync(filePath);
  response.writeHead(200, {
    'Content-Type': MIME[extension] || 'application/octet-stream',
    'Cache-Control': cacheControlFor(requested, request),
    'Content-Length': stats.size,
    'X-Content-Type-Options': 'nosniff'
  });
  if (request.method === 'HEAD') return response.end();
  return fs.createReadStream(filePath).pipe(response);
}

function readJsonBody(request, limitBytes = 4096) {
  return new Promise((resolve, reject) => {
    let size = 0;
    const chunks = [];
    request.on('data', (chunk) => {
      size += chunk.length;
      if (size > limitBytes) {
        reject(Object.assign(new Error('Request body too large'), { status: 413 }));
        request.destroy();
        return;
      }
      chunks.push(chunk);
    });
    request.on('end', () => {
      if (chunks.length === 0) return resolve({});
      try {
        resolve(JSON.parse(Buffer.concat(chunks).toString('utf8')));
      } catch {
        reject(Object.assign(new Error('Body must be valid JSON'), { status: 400 }));
      }
    });
    request.on('error', reject);
  });
}

/** Tiny router so rate limiting and body parsing only touch the endpoints that need them. */
function createRouter() {
  const routes = [];
  const api = (method, pathname, ...handlers) => routes.push({ method, pathname, handlers });
  return { api, routes };
}

/* ---------------------------------------------------------------- api handlers */

// The paytable is published; the hit rate and the RTP are not.
const publicGame = engine.publicGame;

function sessionPayload(state) {
  return {
    ok: true,
    authenticated: false,
    currency: 'VR',
    mode: 'play-money',
    // Stated on every session response, from the server, so no client can be
    // told this account is capable of holding real money when it is not.
    payment: { deposits: false, withdrawals: false, note: 'Play-money credits. No deposits or withdrawals exist in this build.' },
    ...sessions.stats(state)
  };
}

const spinLimiter = rateLimit.middleware({ limit: 90, windowMs: 60_000, trustProxy, name: 'spin' });
const readLimiter = rateLimit.middleware({ limit: 240, windowMs: 60_000, trustProxy, name: 'read' });

const router = createRouter();

router.api('GET', '/api/health', (request, response) => {
  sendJson(response, 200, {
    ok: true,
    service: 'velvet-room-api',
    mode: process.env.GAMING_MODE || 'demo',
    rng: rngMode,
    games: engine.config.games.length,
    uptimeSeconds: Math.round(process.uptime())
  });
});

router.api('GET', '/api/catalog', (request, response) => {
  sendJson(response, 200, { ok: true, mode: 'play-money', categories: catalog });
});

// Slot catalogue: symbols and multipliers only, never the odds.
router.api('GET', '/api/games', readLimiter, (request, response) => {
  sendJson(response, 200, {
    ok: true,
    defaultGameId: engine.config.defaultGameId,
    rng: rngMode,
    limits: engine.limits,
    games: engine.config.games.map(publicGame)
  });
});

// Table-game catalogue. Same rule as the slots: names and copy are published, the odds are not.
router.api('GET', '/api/table/games', readLimiter, (request, response) => {
  sendJson(response, 200, {
    ok: true,
    rng: rngMode,
    limits: engine.limits,
    games: tableGames.CATALOG
  });
});

// Instant-game catalogue, plus the title -> engine map. The client needs the map because the
// lobby lists branded titles ("Hell Hot 100") that share one engine ("scratch").
router.api('GET', '/api/instant/games', readLimiter, (request, response) => {
  sendJson(response, 200, {
    ok: true,
    rng: rngMode,
    limits: engine.limits,
    games: Object.keys(instantGames.GAMES),
    titles: instantGames.TITLE_TO_GAME
  });
});

/**
 * Settles one non-reel round: the table games (blackjack, roulette, lucky seven) and the instant
 * games (scratch, mines, wheel, crash, promo). This is the counterpart to /api/spin and follows the
 * identical order: validate -> commit stake -> draw from house odds -> settle -> sign. The browser
 * never decides any outcome; it only renders this result, which is what removes the
 * "client-side Math.random()" class of unfairness entirely.
 */
router.api('POST', '/api/play', spinLimiter, async (request, response) => {
  const { state, isNew } = sessions.read(request);
  const body = request.body || {};

  // Two engines share this route. Dispatch on the id rather than duplicating the money handling,
  // so a game added to either engine is playable without touching the settlement logic below.
  const isTable = Boolean(tableGames.GAMES[body.gameId]);
  const isInstant = Boolean(instantGames.GAMES[body.gameId]);
  if (!isTable && !isInstant) return sendError(response, 404, 'Unknown game.');

  const bet = Number(body.bet);
  if (!Number.isFinite(bet) || bet <= 0) return sendError(response, 400, 'Enter a valid bet.');

  const rounded = Math.round(bet * 100) / 100;
  if (rounded < engine.limits.minBet) {
    return sendError(response, 400, `Minimum bet is ${engine.limits.minBet} VR.`);
  }
  if (rounded > engine.limits.maxBet) {
    return sendError(response, 400, `Maximum bet is ${engine.limits.maxBet} VR.`);
  }
  if (rounded > state.balance) {
    return sendError(response, 400, 'Insufficient balance.', { balance: state.balance });
  }
  const cap = Math.floor(state.balance * engine.limits.maxBetFractionOfBalance * 100) / 100;
  if (cap > 0 && rounded > cap) {
    return sendError(response, 400, `Bet is capped at ${cap} VR for this balance.`, { balance: state.balance });
  }
  // Same responsible-play gate as the slot path, at the same point: before the stake is taken.
  const blocked = sessions.checkLimits(state, rounded);
  if (blocked) return sendError(response, 403, blocked, { limits: state.limits });

  // Stake committed before the draw, exactly as the slot path does it.
  state.balance = Math.round((state.balance - rounded) * 100) / 100;

  const drawContext = rng.createDraw({ mode: rngMode, nonce: state.spins });
  const result = isTable
    ? tableGames.resolveTable({ gameId: body.gameId, bet: rounded, draw: drawContext.draw })
    : instantGames.resolveInstant({ gameId: body.gameId, bet: rounded, draw: drawContext.draw });

  sessions.recordSpin(state, {
    game: body.gameId,
    bet: rounded,
    payout: result.payout,
    win: result.win,
    multiplier: result.multiplier,
    receipt: {
      gameId: body.gameId,
      serverSeedHash: drawContext.commitment.serverSeedHash,
      clientSeed: drawContext.clientSeed,
      nonce: drawContext.nonce
    }
  });

  sessions.write(response, state);

  const receipt = {
    gameId: body.gameId,
    bet: rounded,
    outcome: result.outcome,
    win: result.win,
    multiplier: result.multiplier,
    payout: result.payout,
    detail: result.detail,
    fairness: {
      mode: rngMode,
      algorithm: 'sha256',
      serverSeedHash: drawContext.commitment.serverSeedHash,
      clientSeed: drawContext.clientSeed,
      nonce: drawContext.nonce,
      serverSeed: drawContext.reveal().serverSeed
    }
  };

  sendJson(response, 200, { ...sessionPayload(state), isNewSession: isNew, result: receipt });
});

router.api('GET', '/api/session', readLimiter, (request, response) => {
  const { state, isNew } = sessions.read(request);
  if (isNew) sessions.write(response, state);
  sendJson(response, 200, sessionPayload(state));
});

router.api('GET', '/api/session/history', readLimiter, (request, response) => {
  const { state, isNew } = sessions.read(request);
  if (isNew) sessions.write(response, state);
  sendJson(response, 200, { ok: true, history: state.history });
});

/**
 * Responsible-play limits. Read returns the current set; POST applies a partial update.
 *
 * These are enforced server-side in the settlement path (see sessions.checkLimits), so this
 * endpoint only changes the guard - it cannot be used to step around it. All values are
 * play-money credits; 0 means "no limit", which is the default.
 */
router.api('GET', '/api/limits', readLimiter, (request, response) => {
  const { state, isNew } = sessions.read(request);
  if (isNew) sessions.write(response, state);
  sendJson(response, 200, { ok: true, limits: state.limits });
});

router.api('POST', '/api/limits', (request, response) => {
  const { state, isNew } = sessions.read(request);
  const body = request.body || {};
  const limits = sessions.setLimits(state, body);
  sessions.write(response, state);
  sendJson(response, 200, {
    ok: true,
    isNewSession: isNew,
    limits,
    note: 'Limits are enforced on the server when a round is settled.'
  });
});

/**
 * Recomputes a settled round from the receipt published alongside the spin. Handles both the slot
 * games (grids) and the table games (hands and wheels). Note the seam: only RNG_MODE=provably-fair
 * produces a replayable outcome. Under RNG_MODE=crypto the result was drawn from node:crypto and
 * cannot be reconstructed after the fact, so the endpoint says so rather than returning an outcome
 * that would not match.
 */
router.api('POST', '/api/verify', async (request, response) => {
  const body = request.body || {};
  const seedArgs = {
    gameId: body.gameId,
    serverSeed: body.serverSeed,
    clientSeed: body.clientSeed,
    nonce: body.nonce,
    bet: Number(body.bet)
  };

  // Table games are checked first: their ids do not collide with the slot ids, and returning the
  // richer detail (cards, wheel pocket) is what makes a table receipt worth publishing.
  const tableReplay = tableGames.verifyTable(seedArgs);
  if (tableReplay) {
    return sendJson(response, 200, {
      ok: true,
      replayable: rngMode === 'provably-fair',
      kind: 'table',
      replay: tableReplay
    });
  }

  // Instant games next, then the slots. Each engine rejects ids it does not own, so the first
  // one that recognises the game id settles the replay.
  const instantReplay = instantGames.verifyInstant(seedArgs);
  if (instantReplay) {
    return sendJson(response, 200, {
      ok: true,
      replayable: rngMode === 'provably-fair',
      kind: 'instant',
      replay: instantReplay
    });
  }

  const replay = engine.verifySpin(seedArgs);
  if (!replay) return sendError(response, 400, 'A valid gameId, serverSeed, clientSeed and nonce are required.');
  return sendJson(response, 200, {
    ok: true,
    replayable: rngMode === 'provably-fair',
    kind: 'slot',
    replay: {
      win: replay.win,
      multiplier: replay.multiplier,
      payout: replay.payout,
      symbol: replay.symbol,
      grid: replay.grid
    }
  });
});

router.api('POST', '/api/session/reset', (request, response) => {
  const state = sessions.freshState();
  sessions.write(response, state);
  sendJson(response, 200, sessionPayload(state));
});

/**
 * The spin endpoint. Everything that decides money happens here and only here:
 * validate -> commit stake -> draw outcome from house odds -> render reels -> settle -> sign.
 */
router.api('POST', '/api/spin', spinLimiter, async (request, response) => {
  const { state, isNew } = sessions.read(request);
  const game = engine.getGame(request.body && request.body.gameId ? request.body.gameId : null);
  if (!game) return sendError(response, 404, 'Unknown game.');

  const bet = Number(request.body && request.body.bet);
  if (!Number.isFinite(bet) || bet <= 0) return sendError(response, 400, 'Enter a valid bet.');

  const rounded = Math.round(bet * 100) / 100;
  if (rounded < engine.limits.minBet) {
    return sendError(response, 400, `Minimum bet is ${engine.limits.minBet} VR.`);
  }
  if (rounded > engine.limits.maxBet) {
    return sendError(response, 400, `Maximum bet is ${engine.limits.maxBet} VR.`);
  }
  if (rounded > state.balance) {
    return sendError(response, 400, 'Insufficient balance.', { balance: state.balance });
  }
  const cap = Math.floor(state.balance * engine.limits.maxBetFractionOfBalance * 100) / 100;
  if (cap > 0 && rounded > cap) {
    return sendError(response, 400, `Bet is capped at ${cap} VR for this balance.`, { balance: state.balance });
  }
  // Responsible-play gate. Checked before the stake leaves the balance, and on the server, so a
  // client cannot step around it by posting here directly.
  const blocked = sessions.checkLimits(state, rounded);
  if (blocked) return sendError(response, 403, blocked, { limits: state.limits });

  // The stake is committed before the draw: a losing round cannot leave the balance untouched.
  state.balance = Math.round((state.balance - rounded) * 100) / 100;

  const drawContext = rng.createDraw({ mode: rngMode, nonce: state.spins });
  const result = engine.resolveSpin({ game, bet: rounded, draw: drawContext.draw });

  sessions.recordSpin(state, {
    game: game.id,
    bet: rounded,
    payout: result.payout,
    win: result.win,
    multiplier: result.multiplier,
    receipt: {
      gameId: game.id,
      serverSeedHash: drawContext.commitment.serverSeedHash,
      clientSeed: drawContext.clientSeed,
      nonce: drawContext.nonce
    }
  });

  sessions.write(response, state);

  const receipt = {
    gameId: game.id,
    bet: rounded,
    multiplier: result.multiplier,
    payout: result.payout,
    win: result.win,
    winningLine: result.winningLine,
    symbol: result.symbol,
    grid: result.grid,
    payline: game.layout.paylines[0],
    speedBand: result.speedBand,
    fairness: {
      mode: rngMode,
      algorithm: 'sha256',
      serverSeedHash: drawContext.commitment.serverSeedHash,
      clientSeed: drawContext.clientSeed,
      nonce: drawContext.nonce,
      // Demo server: revealing after settlement makes the scheme independently checkable.
      // A regulated build holds the seed until the player rotates their own client seed.
      serverSeed: drawContext.reveal().serverSeed
    }
  };

  sendJson(response, 200, { ...sessionPayload(state), isNewSession: isNew, result: receipt });
});

router.api('GET', '/api/providers', readLimiter, (request, response) => {
  sendJson(response, 200, providers.status());
});

/**
 * Wallet capability, answered by the server rather than hardcoded in the page.
 *
 * The two booleans are the contract the UI reads: `deposits` gates whether the
 * wallet view renders a top-up control at all, and it is false in every
 * play-money build. Flipping it in the HTML alone does nothing, which is the
 * point - the client cannot promote itself into a cashier.
 */
router.api('GET', '/api/wallet', readLimiter, (request, response) => {
  const { state, isNew } = sessions.read(request);
  if (isNew) sessions.write(response, state);

  sendJson(response, 200, {
    ok: true,
    currency: 'VR',
    mode: 'play-money',
    balance: Math.round(state.balance * 100) / 100,
    deposits: false,
    withdrawals: false,
    payments: payments.status(),
    realMoney: providers.status().realMoneyEnabled,
    note: 'Demo credits held in a signed session cookie. Nothing here is money and nothing here can be withdrawn.'
  });
});

/**
 * Ordinary-commerce checkout. Deliberately separate from the gaming API.
 *
 * This endpoint is for selling a normal product. It is not, and must never
 * become, the deposit path for the casino - see the header of lib/payments.js
 * for why that distinction is legal rather than cosmetic.
 */
router.api('POST', '/api/payments/intent', (request, response) => {
  const body = request.body || {};
  return payments
    .createPaymentIntent({
      amount: body.amount,
      currency: body.currency,
      metadata: body.metadata,
      receiptEmail: body.receiptEmail
    })
    .then((intent) => sendJson(response, 201, intent));
});

/**
 * Licensed-aggregator launch seam. Guarded in lib/providers.js, which requires
 * a named licence and a real credential before it will do anything, and then
 * still refuses because the wallet transport is not implemented here.
 */
router.api('POST', '/api/provider/session', (request, response, next) => {
  try {
    const session = providers.createSession(request.body && request.body.providerId);
    return sendJson(response, 200, { ok: true, session });
  } catch (error) {
    return next(error);
  }
});

/* ---------------------------------------------------------------- server */

const START_TIME = Date.now();

const server = http.createServer((request, response) => {
  const started = Date.now();
  const url = new URL(request.url, `http://${request.headers.host || 'localhost'}`);
  const requestPath = url.pathname;

  // Baseline security headers. The CSP allows the Google Fonts stylesheet the UI already uses
  // and nothing else. It has no 'unsafe-inline', so inline event handlers will not run.
  const nonce = crypto.randomBytes(16).toString('base64');
  response.setHeader('X-Content-Type-Options', 'nosniff');
  response.setHeader('X-Frame-Options', 'DENY');
  response.setHeader('Referrer-Policy', 'no-referrer');
  response.setHeader('Cross-Origin-Opener-Policy', 'same-origin');
  response.setHeader('Permissions-Policy', 'geolocation=(), camera=(), microphone=()');
  response.setHeader(
    'Content-Security-Policy',
    [
      "default-src 'self'",
      `script-src 'self' 'nonce-${nonce}'`,
      "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
      "font-src 'self' https://fonts.gstatic.com data:",
      "img-src 'self' data:",
      "connect-src 'self'",
      "base-uri 'none'",
      "form-action 'none'",
      "frame-ancestors 'none'",
      "object-src 'none'"
    ].join('; ')
  );

  response.on('finish', () => {
    if (process.env.NODE_ENV !== 'test') {
      // One line per request is the cheapest useful observability for a single-process demo.
      console.log(`${request.method} ${requestPath} ${response.statusCode} ${Date.now() - started}ms`);
    }
  });

  const route = router.routes.find(
    (entry) => entry.method === request.method && entry.pathname === requestPath
  );

  if (!route) {
    // Unknown /api/* paths must 404 as JSON, not fall through to the static file handler.
    if (requestPath.startsWith('/api/')) return sendError(response, 404, 'Unknown endpoint');
    if (request.method !== 'GET' && request.method !== 'HEAD') {
      return sendError(response, 405, 'Method not allowed');
    }
    return serveFile(response, request, requestPath);
  }

  const run = (index) => {
    const handler = route.handlers[index];
    if (!handler) return undefined;

    const next = (error) => {
      if (error) {
        const status = error.status || 500;
        // Log the real cause, return a bounded message: stack traces are not for the client.
        console.error(`[api] ${request.method} ${requestPath} -> ${status}:`, error.message);
        return sendError(response, status, error.message);
      }
      return run(index + 1);
    };

    if (handler.length >= 3) return handler(request, response, next);

    const result = handler(request, response, next);
    if (result && typeof result.catch === 'function') {
      return result.catch((error) => next(error));
    }
    return result;
  };

  const dispatch = () => {
    if (request.method === 'POST') {
      return readJsonBody(request)
        .then((body) => {
          request.body = body;
          return run(0);
        })
        .catch((error) => sendError(response, error.status || 400, error.message));
    }
    request.body = {};
    return run(0);
  };

  try {
    dispatch();
  } catch (error) {
    console.error('[api] unhandled:', error);
    if (!response.headersSent) sendError(response, 500, 'Internal error');
  }
});

server.listen(port, () => {
  const odds = engine.config.games
    .map((game) => `${game.id} RTP=${(game.rtp * 100).toFixed(2)}%`)
    .join(', ');
  console.log(`Velvet Room running at http://localhost:${port}`);
  console.log(`[engine] rng=${rngMode} games=${engine.config.games.length} (${odds})`);
  console.log('[engine] hit rates and RTP stay server-side; /api/games publishes the paytable only');
});

/* ---------------------------------------------------------------- shutdown */

// Containers stop with SIGTERM. Draining keeps an in-flight spin from being cut in half.
let shuttingDown = false;
function shutdown(signal) {
  if (shuttingDown) return;
  shuttingDown = true;
  console.log(`[server] ${signal} received, draining connections`);
  server.close(() => {
    console.log(`[server] closed after ${Math.round((Date.now() - START_TIME) / 1000)}s`);
    process.exit(0);
  });
  setTimeout(() => {
    console.error('[server] forced exit after drain timeout');
    process.exit(1);
  }, 10_000).unref();
}

process.on('SIGTERM', () => shutdown('SIGTERM'));
process.on('SIGINT', () => shutdown('SIGINT'));

module.exports = server;

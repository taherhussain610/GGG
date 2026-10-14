'use strict';

const crypto = require('node:crypto');

/**
 * Signed, stateless session tokens.
 *
 * The balance and the bet history travel inside the token, signed with an HMAC. That keeps the
 * demo server dependency-free and restart-safe, and it means a player cannot edit their balance
 * in devtools: a tampered token fails signature verification and is replaced with a fresh one.
 *
 * Honest limitations, stated plainly because they matter for anything real:
 *   - A token is a bearer credential. Without HTTPS it is exposed to anyone on the path.
 *   - There is no revocation, no rotation on privilege change and no server-side audit trail.
 *   - In-memory sessions would be the wrong fix; a database with a real ledger is.
 * Replace this module with database-backed accounts before any real-money traffic.
 */

const SECRET = (() => {
  const fromEnv = process.env.SESSION_SECRET;
  if (fromEnv && fromEnv.length >= 32) return fromEnv;
  if (process.env.NODE_ENV === 'production' && process.env.ALLOW_EPHEMERAL_SECRET !== 'true') {
    throw new Error(
      'SESSION_SECRET must be set to at least 32 characters in production. ' +
      'Generate one with: node -e "console.log(require(\'crypto\').randomBytes(32).toString(\'hex\'))"'
    );
  }
  console.warn('[session] SESSION_SECRET is unset; using an ephemeral key. Sessions will not survive a restart.');
  return crypto.randomBytes(32).toString('hex');
})();

const COOKIE_NAME = 'vr_session';
const DEFAULT_BALANCE = 1000;
const MAX_HISTORY = 40;

function base64url(buffer) {
  return Buffer.from(buffer).toString('base64url');
}

function sign(payload) {
  return crypto.createHmac('sha256', SECRET).update(payload).digest('base64url');
}

/** Constant-time comparison that tolerates length differences without throwing. */
function safeEqual(a, b) {
  const left = Buffer.from(String(a));
  const right = Buffer.from(String(b));
  if (left.length !== right.length) return false;
  return crypto.timingSafeEqual(left, right);
}

function encode(state) {
  const payload = base64url(JSON.stringify(state));
  return `${payload}.${sign(payload)}`;
}

function decode(token) {
  if (typeof token !== 'string' || !token.includes('.')) return null;
  const [payload, signature] = token.split('.');
  if (!payload || !signature) return null;
  if (!safeEqual(signature, sign(payload))) return null;
  try {
    return JSON.parse(Buffer.from(payload, 'base64url').toString('utf8'));
  } catch {
    return null;
  }
}

function parseCookies(header) {
  const out = {};
  if (typeof header !== 'string') return out;
  for (const part of header.split(';')) {
    const index = part.indexOf('=');
    if (index < 0) continue;
    out[part.slice(0, index).trim()] = decodeURIComponent(part.slice(index + 1).trim());
  }
  return out;
}

const DEFAULT_LIMITS = {
  sessionLossLimit: 0, // 0 = no limit. Credits, not currency.
  sessionStakeLimit: 0,
  sessionMinutes: 0, // 0 = no timer
  selfExcluded: false
};

function freshState() {
  return {
    v: 1,
    balance: DEFAULT_BALANCE,
    staked: 0,
    returned: 0,
    spins: 0,
    wins: 0,
    history: [],
    limits: { ...DEFAULT_LIMITS },
    createdAt: new Date().toISOString(),
    startedAt: Date.now()
  };
}

/** Narrow server-side shape check. A signed but stale token must not crash the game loop. */
function normalize(state) {
  if (!state || typeof state !== 'object') return freshState();
  const normalized = freshState();
  for (const key of ['balance', 'staked', 'returned', 'spins', 'wins']) {
    if (Number.isFinite(state[key]) && state[key] >= 0) normalized[key] = state[key];
  }
  if (Array.isArray(state.history)) normalized.history = state.history.slice(0, MAX_HISTORY);
  normalized.createdAt = typeof state.createdAt === 'string' ? state.createdAt : normalized.createdAt;
  normalized.startedAt = Number.isFinite(state.startedAt) ? state.startedAt : Date.now();

  // Limits are re-validated here as well as on write: a token is a bearer credential, so a stale
  // or hand-edited one must not be able to widen its own limit.
  const limits = state.limits && typeof state.limits === 'object' ? state.limits : {};
  normalized.limits = {
    sessionLossLimit: clampLimit(limits.sessionLossLimit),
    sessionStakeLimit: clampLimit(limits.sessionStakeLimit),
    sessionMinutes: clampLimit(limits.sessionMinutes, 1440),
    selfExcluded: limits.selfExcluded === true
  };
  return normalized;
}

/** A limit is a non-negative number, and capped so a typo cannot set a 10^12 ceiling. */
function clampLimit(value, max = 1_000_000) {
  const numeric = Number(value);
  if (!Number.isFinite(numeric) || numeric < 0) return 0;
  return Math.min(Math.round(numeric * 100) / 100, max);
}

/**
 * The responsible-play gate. Returns null when a round may proceed, or a reason string when the
 * player's own limit blocks it.
 *
 * This is enforced on the server, in the settlement path, and not in the browser. A client-side
 * check is a suggestion: it disappears the moment someone opens devtools or posts to /api/spin
 * directly, which is exactly the person a loss limit needs to hold for. The limits here are
 * play-money guards (this build holds no cash), but the enforcement point is the one that would
 * matter if it did.
 */
function checkLimits(state, bet) {
  const limits = state.limits || DEFAULT_LIMITS;

  if (limits.selfExcluded) {
    return 'Self-exclusion is active on this session. Reset the session to play again.';
  }
  if (limits.sessionMinutes > 0) {
    const elapsedMinutes = (Date.now() - state.startedAt) / 60000;
    if (elapsedMinutes > limits.sessionMinutes) {
      return `Session time limit of ${limits.sessionMinutes} minute(s) reached.`;
    }
  }
  if (limits.sessionLossLimit > 0) {
    const loss = Math.round((state.staked - state.returned) * 100) / 100;
    if (loss + Number(bet) > limits.sessionLossLimit) {
      return `Session loss limit of ${limits.sessionLossLimit} VR reached.`;
    }
  }
  if (limits.sessionStakeLimit > 0 && state.staked + Number(bet) > limits.sessionStakeLimit) {
    return `Session stake limit of ${limits.sessionStakeLimit} VR reached.`;
  }
  return null;
}

/** Applies a partial limits update, ignoring unknown keys. */
function setLimits(state, patch = {}) {
  const current = state.limits || DEFAULT_LIMITS;
  state.limits = {
    sessionLossLimit: 'sessionLossLimit' in patch ? clampLimit(patch.sessionLossLimit) : current.sessionLossLimit,
    sessionStakeLimit:
      'sessionStakeLimit' in patch ? clampLimit(patch.sessionStakeLimit) : current.sessionStakeLimit,
    sessionMinutes: 'sessionMinutes' in patch ? clampLimit(patch.sessionMinutes, 1440) : current.sessionMinutes,
    selfExcluded: 'selfExcluded' in patch ? patch.selfExcluded === true : current.selfExcluded
  };
  return state.limits;
}

/** Reads the session from the request, or hands back a brand new one. */
function read(request) {
  const cookies = parseCookies(request.headers.cookie);
  const state = decode(cookies[COOKIE_NAME]);
  return { state: normalize(state), isNew: !state };
}

function write(response, state) {
  const secure = process.env.NODE_ENV === 'production' ? '; Secure' : '';
  response.setHeader(
    'Set-Cookie',
    `${COOKIE_NAME}=${encode(state)}; Path=/; HttpOnly; SameSite=Strict; Max-Age=86400${secure}`
  );
}

function recordSpin(state, { game, bet, payout, win, multiplier, receipt }) {
  state.balance = Math.round((state.balance + payout) * 100) / 100;
  state.staked = Math.round((state.staked + bet) * 100) / 100;
  state.returned = Math.round((state.returned + payout) * 100) / 100;
  state.spins += 1;
  if (win) state.wins += 1;
  state.history.unshift({
    at: new Date().toISOString(),
    game,
    bet,
    payout,
    win,
    multiplier,
    receipt
  });
  state.history = state.history.slice(0, MAX_HISTORY);
  return state;
}

/** Accountability figures derived from the ledger carried in the token. */
function stats(state) {
  const net = Math.round((state.returned - state.staked) * 100) / 100;
  return {
    balance: Math.round(state.balance * 100) / 100,
    staked: state.staked,
    returned: state.returned,
    net,
    spins: state.spins,
    wins: state.wins,
    history: state.history.slice(0, 10),
    limits: state.limits,
    startedAt: state.startedAt
  };
}

module.exports = {
  read,
  write,
  stats,
  recordSpin,
  freshState,
  checkLimits,
  setLimits,
  DEFAULT_LIMITS,
  DEFAULT_BALANCE,
  COOKIE_NAME
};

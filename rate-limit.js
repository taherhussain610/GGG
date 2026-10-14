'use strict';

/**
 * Fixed-window rate limiter, keyed by client address.
 *
 * Scope and honesty: this stops a single client hammering the spin endpoint, which is the
 * realistic abuse path for a demo. It is not a bot defence. Behind a reverse proxy it needs
 * `trustProxy` so the key is the real client address, not the proxy, and the map is per process,
 * so a multi-instance deployment needs a shared store (Redis) instead.
 */

const buckets = new Map();

/** Evicts idle buckets so a long-running process cannot grow the map without bound. */
const SWEEP_INTERVAL_MS = 60_000;

function clientKey(request, trustProxy) {
  if (trustProxy) {
    const forwarded = request.headers['x-forwarded-for'];
    if (typeof forwarded === 'string' && forwarded.length) {
      const first = forwarded.split(',')[0].trim();
      if (first) return first;
    }
  }
  return request.socket.remoteAddress || 'unknown';
}

/**
 * @returns {{ allowed: boolean, remaining: number, retryAfter: number }}
 */
function take(key, limit, windowMs, now = Date.now()) {
  let bucket = buckets.get(key);
  if (!bucket || now >= bucket.resetAt) {
    bucket = { count: 0, resetAt: now + windowMs };
    buckets.set(key, bucket);
  }
  bucket.count += 1;
  if (bucket.count > limit) {
    return { allowed: false, remaining: 0, retryAfter: Math.ceil((bucket.resetAt - now) / 1000) };
  }
  return { allowed: true, remaining: limit - bucket.count, retryAfter: 0 };
}

/** Express-style middleware factory, kept dependency-free. */
function middleware({ limit = 60, windowMs = 60_000, trustProxy = false, name = 'default' } = {}) {
  return function rateLimit(request, response, next) {
    // Only count the requests that actually reach the guarded endpoint. Checking the method and
    // path here keeps the limiter honest when it is mounted in front of a small router, where a
    // shared bucket would otherwise also swallow the cheap read-only calls.
    const method = (request.method || 'GET').toUpperCase();
    // Every POST is money-touching (spin, play, verify, payments), so the limiter guards the
    // whole method rather than a hand-maintained path list that would silently miss a new route.
    const guarded = method === 'POST';
    if (!guarded) return next();

    const result = take(`${name}:${clientKey(request, trustProxy)}`, limit, windowMs);
    response.setHeader('X-RateLimit-Limit', String(limit));
    response.setHeader('X-RateLimit-Remaining', String(result.remaining));
    if (!result.allowed) {
      response.setHeader('Retry-After', String(result.retryAfter));
      return next(Object.assign(new Error('Too many requests. Slow down.'), { status: 429 }));
    }
    return next();
  };
}

const sweep = setInterval(() => {
  const now = Date.now();
  for (const [key, bucket] of buckets) {
    if (now >= bucket.resetAt) buckets.delete(key);
  }
}, SWEEP_INTERVAL_MS);
sweep.unref();

module.exports = { middleware, take, buckets };

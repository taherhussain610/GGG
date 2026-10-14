'use strict';

const crypto = require('node:crypto');

/**
 * Cryptographically secure random helpers.
 *
 * Two modes are exposed, matching how regulated aggregators classify RNG:
 *
 *  - crypto : node:crypto.randomInt / randomBytes, the default, unbeatable in-process entropy.
 *  - provably-fair : SHA-256(serverSeed : clientSeed : nonce) expanded into a stream.
 *                    The server seed hash is published before the round and the seed itself
 *                    is revealed afterwards, so a player can recompute the exact outcome.
 *
 * Nothing here ever reaches the browser; the client only ever sees a commit hash.
 */

/** Uniform integer in [0, maxExclusive) with rejection sampling, so there is no modulo bias. */
function randInt(maxExclusive) {
  if (!Number.isInteger(maxExclusive) || maxExclusive <= 0) {
    throw new RangeError('randInt requires a positive integer bound');
  }
  if (maxExclusive === 1) return 0;

  // Largest multiple of maxExclusive that fits in 48 bits.
  const limit = Math.floor(0x1000000000000 / maxExclusive) * maxExclusive;
  for (let attempt = 0; attempt < 64; attempt += 1) {
    const n = crypto.randomBytes(6).readUIntBE(0, 6);
    if (n < limit) return n % maxExclusive;
  }
  // Astronomically unlikely; fall back to the platform implementation.
  return crypto.randomInt(maxExclusive);
}

/** Uniform float in [0, 1) built from 48 bits, so every value is exactly representable. */
function randFloat() {
  return crypto.randomBytes(6).readUIntBE(0, 6) / 2 ** 48;
}

function sha256(value) {
  return crypto.createHash('sha256').update(value).digest('hex');
}

function newServerSeed() {
  return crypto.randomBytes(32).toString('hex');
}

/**
 * Deterministic byte stream derived from the fairness triple.
 * One digest yields 32 bytes; we rehash with a counter to extend it on demand.
 */
function createStream(serverSeed, clientSeed, nonce) {
  let block = 0;
  let buffer = Buffer.alloc(0);
  let offset = 0;

  function ensure(bytes) {
    while (buffer.length - offset < bytes) {
      const digest = crypto
        .createHash('sha256')
        .update(`${serverSeed}:${clientSeed}:${nonce}:${block}`)
        .digest();
      buffer = buffer.length === offset ? digest : Buffer.concat([buffer.subarray(offset), digest]);
      offset = 0;
      block += 1;
    }
  }

  return {
    uint32() {
      ensure(4);
      const value = buffer.readUInt32BE(offset);
      offset += 4;
      return value;
    },
    float() {
      // 53 bits of entropy assembled from two 32-bit draws.
      // high is 27 bits, low is 26 bits: the shift between them is 2**26, and the 53-bit total
      // is 2**53. Multiplying high by 2**27 instead of 2**26 spreads the result over [0, 2),
      // so half of every stream lands above 1.0 and silently beats every hit threshold.
      const high = this.uint32() >>> 5; // 27 bits
      const low = this.uint32() >>> 6; // 26 bits
      return (high * 0x4000000 + low) / 0x20000000000000;
    },
    int(maxExclusive) {
      const limit = Math.floor(0x100000000 / maxExclusive) * maxExclusive;
      for (let attempt = 0; attempt < 64; attempt += 1) {
        const n = this.uint32();
        if (n < limit) return n % maxExclusive;
      }
      return this.uint32() % maxExclusive;
    }
  };
}

/**
 * Single entry point used by the slot engine.
 * Returns a draw interface plus the fairness receipt to hand back to the player.
 */
function createDraw(options = {}) {
  const clientSeed = typeof options.clientSeed === 'string' && options.clientSeed.trim()
    ? options.clientSeed.trim().slice(0, 128)
    : crypto.randomBytes(8).toString('hex');
  const nonce = Number.isInteger(options.nonce) && options.nonce >= 0 ? options.nonce : 0;
  const serverSeed = options.serverSeed || newServerSeed();

  let draw;
  if (options.mode === 'provably-fair') {
    const stream = createStream(serverSeed, clientSeed, nonce);
    draw = { int: (max) => stream.int(max), float: () => stream.float() };
  } else {
    draw = { int: randInt, float: randFloat };
  }

  return {
    clientSeed,
    nonce,
    serverSeed,
    draw,
    commitment: {
      algorithm: 'sha256',
      serverSeedHash: sha256(serverSeed)
    },
    // Only call this after the round is settled.
    reveal: () => ({ serverSeed, clientSeed, nonce })
  };
}

/**
 * Rebuilds the exact draw interface a settled round used, from the disclosed fairness triple.
 * The verifier endpoint and the tests both go through here, so a published receipt really does
 * reproduce the engine bit-for-bit.
 */
function reopen(serverSeed, clientSeed, nonce) {
  const stream = createStream(serverSeed, clientSeed, nonce);
  return { int: (max) => stream.int(max), float: () => stream.float() };
}

module.exports = {
  randInt,
  randFloat,
  sha256,
  newServerSeed,
  createDraw,
  createStream,
  reopen
};

"use strict";

const fs = require("node:fs");
const path = require("node:path");

/**
 * Licensed aggregator boundary.
 *
 * ---------------------------------------------------------------------------
 * Why this module exists instead of a payment integration
 * ---------------------------------------------------------------------------
 * This is the honest architecture for anything involving real money. A licensed
 * aggregator (SOFTSWISS, Hub88, SlotMatrix, Kambi, and the others listed in
 * providers.json) holds the gambling licence, the regulated RNG, the certified
 * game content, the KYC/AML programme and usually the payment institution. This
 * application becomes a client of theirs over their wallet API.
 *
 * The practical consequence is that money never moves through this codebase.
 * This server never sees a card number, never holds player funds and never
 * decides whether a deposit is legal - because it is not the licensed party and
 * cannot legally do any of those things itself.
 *
 * ---------------------------------------------------------------------------
 * Gate status: CLOSED
 * ---------------------------------------------------------------------------
 * Real-money mode cannot be switched on by a config change in this repository.
 * `assertRealMoneyAllowed` requires ALL of:
 *
 *   1. GAMING_MODE != "demo"                      - the operator intends it.
 *   2. A provider key is present in the environment - a real contract exists.
 *   3. RE_ACKNOWLEDGE_LICENCE holds a non-empty value - a human has stated, in
 *      writing in their own deployment config, which licence authorises this
 *      and who holds it.
 *
 * Point 3 is the important one. It is not a technical control and it is not
 * meant to be: it exists so that enabling real-money traffic requires a person
 * to name the licence they are operating under, rather than flipping a boolean
 * and discovering the problem when a regulator does.
 *
 * Even with all three satisfied, `createSession` still refuses, because the
 * wallet calls are not implemented. That refusal is the honest state of this
 * repository: the seam is defined, the transport is not, and no amount of
 * missing code should be papered over with a fake success response.
 */

const root = path.join(__dirname, "..");

function loadRegistry() {
  try {
    const raw = JSON.parse(
      fs.readFileSync(path.join(root, "providers.json"), "utf8"),
    );
    return Array.isArray(raw.providers) ? raw : { mode: "demo", providers: [] };
  } catch (error) {
    console.error(
      `[providers] could not read providers.json: ${error.message}`,
    );
    return { mode: "demo", providers: [] };
  }
}

function loadKeyMap() {
  try {
    const raw = JSON.parse(
      fs.readFileSync(path.join(root, "games.json"), "utf8"),
    );
    return raw.providerKeys || {};
  } catch {
    return {};
  }
}

const registry = loadRegistry();
const keyMap = loadKeyMap();

function gamingMode() {
  return process.env.GAMING_MODE || "demo";
}

/**
 * Resolves the environment variable name that holds a provider credential.
 * Returns the NAME, never the value - that distinction is the whole point.
 */
function envVarFor(provider) {
  return provider.env || keyMap[provider.id] || "";
}

function isConfigured(provider) {
  const envVar = envVarFor(provider);
  return Boolean(envVar && process.env[envVar]);
}

/** Public status. Safe to serve: names and booleans only, never a credential. */
function status() {
  const mode = gamingMode();
  return {
    ok: true,
    mode,
    realMoneyEnabled: mode !== "demo",
    licenceAcknowledged: Boolean(process.env.RE_ACKNOWLEDGE_LICENCE),
    providers: registry.providers.map((provider) => {
      const envVar = envVarFor(provider);
      const configured = isConfigured(provider);
      return {
        id: provider.id,
        name: provider.name,
        category: provider.category || "platform",
        service: provider.service,
        envVar,
        status: configured ? "configured" : "not_configured",
        // A configured key is NOT permission to launch. Say so in the payload
        // so nobody reads "configured" as "ready".
        requiresLicence: true,
      };
    }),
  };
}

function getProvider(id) {
  return registry.providers.find((provider) => provider.id === id) || null;
}

/**
 * The gate described in the header. Throws with a 403 rather than returning a
 * boolean, so a caller cannot accidentally ignore the answer.
 */
function assertRealMoneyAllowed(providerId) {
  const provider = getProvider(providerId);
  if (!provider) {
    const error = new Error(`Unknown provider "${providerId}".`);
    error.status = 404;
    throw error;
  }

  const mode = gamingMode();
  if (mode === "demo") {
    const error = new Error(
      "Real-money mode is disabled (GAMING_MODE=demo). This deployment is a play-money demo only.",
    );
    error.status = 403;
    throw error;
  }

  if (!isConfigured(provider)) {
    const error = new Error(
      `${provider.name} has no credential. Set ${envVarFor(provider)} before enabling real-money traffic.`
    );
    error.status = 503;
    throw error;
  }

  if (!process.env.RE_ACKNOWLEDGE_LICENCE) {
    const error = new Error(
      "RE_ACKNOWLEDGE_LICENCE is not set. Name the licence that authorises real-money play before enabling it.",
    );
    error.status = 403;
    throw error;
  }

  return provider;
}

/**
 * The wallet seam.
 *
 * Every aggregator exposes roughly the same four operations, which is why this
 * interface is worth defining even though it is unimplemented:
 *
 *   createSession  - hand the player off / mint a game launch URL
 *   getBalance     - reconcile the wallet of record
 *   debit          - commit a stake
 *   credit         - settle a win
 *
 * When the contract is signed, this is where the calls go. Note what is NOT
 * here: no card handling, no deposit acceptance, no withdrawal approval. Those
 * belong to the aggregator's own cashier, which is the licensed party.
 */
function createSession(providerId) {
  const provider = assertRealMoneyAllowed(providerId);
  const error = new Error(
    `The ${provider.name} wallet transport is not implemented in this repository. ` +
      "Implement it against the signed contract, then remove this guard.",
  );
  // 501, not 500: this is a deliberate not-implemented, not a fault.
  error.status = 501;
  throw error;
}

module.exports = {
  status,
  getProvider,
  assertRealMoneyAllowed,
  createSession,
  gamingMode,
};

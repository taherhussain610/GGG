"use strict";

const crypto = require("node:crypto");

/**
 * Payment boundary for Velvet Room.
 *
 * ---------------------------------------------------------------------------
 * READ THIS BEFORE WIRING ANYTHING TO A CASINO WALLET
 * ---------------------------------------------------------------------------
 * This module is a Stripe PaymentIntent factory for ORDINARY commerce: a shop,
 * a subscription, a digital product. It has exactly one route in server.js and
 * that route is mounted at /api/payments/*, deliberately NOT part of the
 * gaming API.
 *
 * It must not be used to accept deposits for gambling. Doing so is not a
 * configuration choice, it is the substance of the offence in most
 * jurisdictions, and it is a direct breach of Stripe's own Services Agreement:
 * Stripe prohibits online gambling merchants without prior written approval and
 * a separate agreement. Wiring this endpoint to the slot balance would:
 *
 *   1. Take real money from real people through an unlicensed gambling
 *      operator, which is a criminal offence in most of the US, the UK, the EU
 *      and Australia, among others.
 *   2. Get the merchant account frozen and the funds held the moment Stripe's
 *      risk model reads the descriptor and the site.
 *   3. Strip away every protection a licensed operator must provide: age
 *      verification, KYC, AML, source-of-funds checks, deposit limits,
 *      self-exclusion and the segregated player-funds account.
 *
 * The honest path for real-money play is a licensed aggregator (SOFTSWISS,
 * Hub88, SlotMatrix, Kambi - see providers.json), where the aggregator holds
 * the licence and the payment institution and this application is a client of
 * theirs. lib/providers.js is the seam for that, and it is gated off.
 *
 * ---------------------------------------------------------------------------
 * What this module does do, and does correctly
 * ---------------------------------------------------------------------------
 *   - Keeps STRIPE_SECRET_KEY strictly server-side. It is never returned, never
 *     logged, never embedded in a response body.
 *   - Creates the PaymentIntent server-side and returns only the client secret.
 *   - Requires an explicit amount, so a client cannot dictate the price.
 *   - Refuses to start a payment while real-money gaming is enabled, because
 *     the combination is the thing that is illegal. That check is enforced
 *     here, in the module that talks to Stripe, not in the route that calls
 *     it, so a second caller added later cannot bypass it.
 */

const STRIPE_API = "https://api.stripe.com/v1";

/** Deliberately small: a demo shop cart, not a payment platform. */
const LIMITS = Object.freeze({
  minAmountMinor: 50,
  maxAmountMinor: 500000,
  allowedCurrencies: Object.freeze(["usd", "eur", "gbp"]),
});

/**
 * Secret key resolution. Read once, kept in the closure, never exported.
 * A live key is accepted only when the operator has explicitly acknowledged
 * what that means via ALLOW_LIVE_STRIPE_KEY.
 */
function resolveSecretKey() {
  const key = process.env.STRIPE_SECRET_KEY;
  if (!key) return { configured: false, mode: "unconfigured", key: null };

  if (!/^sk_(test|live)_/.test(key)) {
    return {
      configured: false,
      mode: "invalid",
      key: null,
      reason: "STRIPE_SECRET_KEY must be an sk_test_ or sk_live_ key.",
    };
  }
  if (
    key.startsWith("sk_live_") &&
    process.env.ALLOW_LIVE_STRIPE_KEY !== "true"
  ) {
    return {
      configured: false,
      mode: "live-locked",
      key: null,
      reason:
        'A live Stripe key was supplied but ALLOW_LIVE_STRIPE_KEY is not "true". Refusing to charge real cards.',
    };
  }
  return {
    configured: true,
    mode: key.startsWith("sk_live_") ? "live" : "test",
    key,
  };
}

const secret = resolveSecretKey();

/**
 * The gate. A payment endpoint and a real-money gaming mode must never be
 * enabled at the same time in this codebase.
 */
function assertNotRealMoneyGaming() {
  const mode = process.env.GAMING_MODE || "demo";
  if (mode !== "demo") {
    const error = new Error(
      `Payments are disabled while GAMING_MODE="${mode}". Deposits for real-money gambling require a ` +
        "licence and a payment provider approved for iGaming. Stripe is not that provider by default.",
    );
    error.status = 403;
    throw error;
  }
}

function status() {
  return {
    configured: secret.configured,
    // The mode is reported; the key never is.
    mode: secret.mode,
    reason: secret.reason || null,
    provider: "stripe",
    purpose: "ordinary-commerce-only",
    liveEnabled: secret.mode === "live",
    limits: LIMITS,
  };
}

function badRequest(message) {
  return Object.assign(new Error(message), { status: 400 });
}

/** Integer minor units only. A float here is how you lose a cent per order. */
function normalizeAmount(value) {
  const amount = Number(value);
  if (!Number.isInteger(amount))
    throw badRequest(
      "amount must be an integer in the smallest currency unit (for example 1099 for $10.99).",
    );
  if (amount < LIMITS.minAmountMinor)
    throw badRequest(`amount must be at least ${LIMITS.minAmountMinor}.`);
  if (amount > LIMITS.maxAmountMinor)
    throw badRequest(
      `amount exceeds the ${LIMITS.maxAmountMinor} ceiling for this demo.`,
    );
  return amount;
}

function normalizeCurrency(value) {
  const currency = String(value || "usd").toLowerCase();
  if (!LIMITS.allowedCurrencies.includes(currency)) {
    throw badRequest(
      `currency must be one of: ${LIMITS.allowedCurrencies.join(", ")}.`,
    );
  }
  return currency;
}

/**
 * Metadata is echoed onto the Stripe object, so it travels to the dashboard and
 * the webhook. Keep it to opaque identifiers and never put anything sensitive
 * in it - metadata is not a secret store.
 */
function normalizeMetadata(value) {
  const out = {};
  if (!value || typeof value !== "object") return out;
  for (const [rawKey, rawValue] of Object.entries(value)) {
    const key = String(rawKey)
      .replace(/[^a-zA-Z0-9_-]/g, "")
      .slice(0, 40);
    if (!key) continue;
    if (rawValue === null || rawValue === undefined) continue;
    out[key] = String(rawValue).slice(0, 200);
  }
  return out;
}

/**
 * Creates a PaymentIntent over the REST API with fetch, so this project keeps
 * its zero-dependency posture. Returns the client secret only.
 */
async function createPaymentIntent({
  amount,
  currency,
  metadata,
  receiptEmail,
} = {}) {
  assertNotRealMoneyGaming();

  if (!secret.configured) {
    const error = new Error(
      secret.reason ||
        "Stripe is not configured. Set STRIPE_SECRET_KEY on the server to enable the demo checkout.",
    );
    error.status = 503;
    throw error;
  }

  const params = new URLSearchParams();
  params.set("amount", String(normalizeAmount(amount)));
  params.set("currency", normalizeCurrency(currency));
  // Manual capture is the safer default for an unfamiliar integration: the card
  // is authorised, and nothing is captured until the operator confirms the
  // order actually exists. Remove it only when the fulfilment path is proven.
  params.set("capture_method", "manual");
  params.set("automatic_payment_methods[enabled]", "true");
  if (
    receiptEmail &&
    typeof receiptEmail === "string" &&
    receiptEmail.includes("@")
  ) {
    params.set("receipt_email", receiptEmail.slice(0, 200));
  }
  for (const [key, value] of Object.entries(normalizeMetadata(metadata))) {
    params.set(`metadata[${key}]`, value);
  }

  // Idempotency matters: a retried request must not create a second charge.
  const idempotencyKey = crypto.randomUUID();

  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), 15_000);

  let response;
  try {
    response = await fetch(`${STRIPE_API}/payment_intents`, {
      method: "POST",
      headers: {
        Authorization: `Bearer ${secret.key}`,
        "Content-Type": "application/x-www-form-urlencoded",
        // Pin the API version instead of floating on the account default, so a
        // dashboard-side upgrade cannot silently change this response shape.
        "Stripe-Version": "2024-06-20",
        "Idempotency-Key": idempotencyKey,
      },
      body: params.toString(),
      signal: controller.signal,
    });
  } finally {
    clearTimeout(timeout);
  }

  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    // Stripe's error object has type/code/message. Log the detail server-side,
    // return a bounded message: never leak key state or internal identifiers.
    const detail = payload && payload.error ? payload.error : {};
    console.error(
      `[payments] stripe ${response.status} ${detail.type || ""} ${detail.code || ""}: ${detail.message || "no message"}`,
    );
    const error = new Error(
      response.status === 401
        ? "The payment provider rejected the server credential."
        : "The payment provider could not create this payment.",
    );
    error.status = response.status === 401 ? 502 : 402;
    throw error;
  }

  if (!payload.client_secret) {
    console.error("[payments] stripe returned no client_secret");
    const error = new Error(
      "The payment provider returned an unusable response.",
    );
    error.status = 502;
    throw error;
  }

  return {
    ok: true,
    id: payload.id,
    clientSecret: payload.client_secret,
    amount: payload.amount,
    currency: payload.currency,
    status: payload.status,
    // Publishable key is public by design; it is the browser-side half and is
    // useless without the client secret above.
    publishableKey: process.env.STRIPE_PUBLISHABLE_KEY || null,
  };
}

module.exports = { createPaymentIntent, status, LIMITS };

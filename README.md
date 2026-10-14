# Velvet Room

Velvet Room is a responsive play-money casino interface with local demo APIs. It includes Lobby, Live Tables, Wallet, and Account views, plus Casino, Slots, Sports, Instant, and Crash categories.

The client is `index.html` plus `app.js`, served from the project root. The client JavaScript lives in a separate file rather than an inline `<script>` block so that the strict Content-Security-Policy in `server.js` (`script-src 'self' 'nonce-…'`, no `'unsafe-inline'`) can execute it; an inline block is refused by the browser and nothing on the page would run.

## Dedicated slot games

Three slot machines are defined server-side in [games.json](games.json) and played from the "Slot machines" panel:

- **Golden Orchard** (`golden-orchard`) — fruit reels, 5% hit rate, ADVANCED speed band.
- **Neon Fortune** (`neon-fortune`) — looser reels, 18% hit rate, BYTE speed band.
- **Moon Market** (`moon-market`) — tighter reels, 3% hit rate, ADVANCED speed band.

Each spin is settled by `POST /api/spin` against the house odds in `slot-engine.js` and returns a rendered grid plus a fairness receipt. The browser never sees the hit rate or the weights, only the paytable, which is what `GET /api/games` publishes. Clicking one of these three games in the lobby catalogue opens its machine; other catalogue entries remain display-only.

## Table games

Three table games are settled server-side by `table-games.js`, the counterpart to the slot engine, and played from the "Your next hand" panel:

- **Blackjack** (`blackjack`) — a shuffled shoe, player stands on 17, dealer draws to 17. Pays 2× on a win, 1× on a push.
- **Roulette** (`roulette`) — European single-zero wheel, straight-up bet paying 36× (35:1 plus stake), the standard 2.7% edge.
- **Lucky 7** (`lucky-seven`) — a 1-in-7 draw paying 7×.

A round is settled by `POST /api/play` in the same order as a slot spin: validate, commit the stake, draw from the server's RNG, then settle and sign. The client never decides a table outcome; it renders the hand, the wheel pocket or the draw the server returned, which is why the account page can truthfully say no game here runs on browser randomness. `GET /api/table/games` publishes the game list.

## Instant games

The rest of the lobby - scratch cards, mines, wheels, crash and the promotions - is settled
server-side by `instant-games.js`, the third engine alongside the slots and the tables. Several
branded lobby titles share one engine (one game server, many skins); the title-to-engine map lives
in `instant-games.js` and is published by `GET /api/instant/games`.

- **Scratch** (`scratch`) - three symbols from five; a triple pays 12x, a pair returns 1x. 96% return.
- **Mines** (`mines`) - three safe tiles of five, pays 1.5:1. 90% return.
- **Wheel** (`wheel`) - weighted coloured segments. 93% return.
- **Crash** (`crash-round`) - cash out at a 2x target before the round busts. 96% return.
- **Promotion** (`promo`) - a bonus draw, not a wager: the stake is always returned and any bonus is
  added on top, so its return is above 100% by design. This is the one deliberate exception, and the
  client labels it as a promotion.

Every catalogue card in the lobby resolves to a slot, a table game or an instant game - there is no
display-only card left. `POST /api/play` dispatches to whichever engine owns the game id, and
`POST /api/verify` replays a settled round from any of the three.

## Run locally

Requires Node.js 18 or newer.

```powershell
npm start
```

Open `http://localhost:4173`.

## Hostinger VPS deployment

On an Ubuntu Hostinger VPS with Docker installed:

```bash
git clone <your-repository-url> velvet-room
cd velvet-room
cp .env.example .env
docker compose up -d --build
docker compose ps
curl http://127.0.0.1:4173/api/health
```

Put a reverse proxy and TLS certificate in front of port `4173` before exposing the application publicly. Do not commit `.env` or provider credentials.

## Local API

- `GET /api/health` returns service health.
- `GET /api/catalog` returns the game categories.
- `GET /api/session` returns the local guest session and demo balance.
- `GET /api/games` returns the slot catalogue and paytable, never the odds.
- `POST /api/spin` settles a play-money slot round.
- `GET /api/table/games` returns the table-game catalogue.
- `POST /api/play` settles a play-money table round (blackjack, roulette, lucky seven).
- `POST /api/verify` replays a settled slot or table round from its disclosed seed triple.
- `GET /api/session/history` returns the rounds recorded against the current session.
- `GET /api/wallet` returns the demo balance and the payment capability flags.
- `GET /api/providers` returns the configured provider slots and their setup status.
- `POST /api/provider/session` is the licensed-aggregator launch seam. Guarded.
- `POST /api/payments/intent` creates a Stripe PaymentIntent for ordinary commerce. Guarded.

Provider credentials are intentionally represented by environment-variable names in [providers.json](providers.json); no secret is committed to the repository. Set `PORT` to use another local port, for example `PORT=4174 npm start`. If `npm start` reports that port `4173` is already in use, the existing server can be reused at `http://localhost:4173` or stopped before restarting.

The browser loads these endpoints when served over HTTP and falls back to its embedded catalog when opened directly as a file.

## Payments

There are two distinct payment paths in this repository and they are not interchangeable.

### 1. The wallet is play-money only

`GET /api/wallet` reports `deposits: false` and `withdrawals: false`, and the Wallet view renders a notice from those flags. The balance is a signed session cookie, not money, and it cannot be withdrawn. Nothing in this build accepts a card for the casino.

### 2. `/api/payments/intent` is for ordinary commerce

It exists so that a normal product — a shop, a subscription, a digital good — can be sold on the same deployment. It is deliberately kept out of the gaming API.

It will **not** work as a deposit path, by design:

- `lib/payments.js` calls `assertNotRealMoneyGaming()` before it will talk to Stripe. With `GAMING_MODE` set to anything other than `demo`, the route returns `403` and no PaymentIntent is created.
- A live key (`sk_live_…`) is refused unless `ALLOW_LIVE_STRIPE_KEY=true` is set explicitly.
- The secret key never leaves the server process. Only the client secret and the publishable key are returned.

That first point is a legal distinction, not a style preference. Wiring a payment endpoint to the slot balance means operating an unlicensed gambling business: it takes real money from real people without age verification, KYC, AML, source-of-funds checks or segregated player funds, and it is a criminal offence in most jurisdictions where this could be deployed. It is also a direct breach of Stripe's Services Agreement, which prohibits online gambling merchants without prior written approval — the merchant account would be frozen and the funds held.

### 3. Real-money play goes through a licensed aggregator

`lib/providers.js` is the seam for the lawful architecture. A licensed aggregator (SOFTSWISS, Hub88, SlotMatrix, Kambi, and the others in [providers.json](providers.json)) holds the gambling licence, the certified RNG, the KYC/AML programme and usually the payment institution. This application becomes a client of theirs over their wallet API, and **money never moves through this codebase**: no card handling, no deposit acceptance, no withdrawal approval.

`POST /api/provider/session` refuses unless all of the following hold:

1. `GAMING_MODE` is not `demo`;
2. the relevant provider credential is present in the environment;
3. `RE_ACKNOWLEDGE_LICENCE` is non-empty, naming the licence that authorises the deployment.

Even then it returns `501 Not Implemented`, because the wallet transport is genuinely not written. That refusal is the honest state of this repository: the seam is defined, the transport is not, and a fake success response would be worse than an honest gap.

## Production integration boundary

This repository intentionally contains no real-money payment, identity, sports-data, or regulated RNG integration. Those require a licensed provider, server-side secrets, authentication, age/KYC/AML controls, audited game logic, responsible-play limits, and jurisdiction-specific compliance before production use.

The container is therefore safe to deploy as a demo or integration shell only. Real-money mode must remain disabled until the licensing and provider approval checklist is complete.

### Before enabling real-money traffic

- [ ] Signed contract with a licensed aggregator, and the licence named in `RE_ACKNOWLEDGE_LICENCE`.
- [ ] Age verification and KYC/AML at account creation, not at first withdrawal.
- [ ] A database-backed ledger replacing the signed-cookie session in `lib/sessions.js`.
- [ ] `SESSION_SECRET` set to at least 32 random characters.
- [ ] Independent RNG audit, and `RNG_MODE=provably-fair` if receipts need to be replayable.
- [ ] Responsible-play controls: deposit limits, loss limits, session reminders, self-exclusion.
- [ ] Geo-blocking and a per-jurisdiction entitlement check.
- [ ] HTTPS in front of the application, with `TRUST_PROXY=true` so rate limiting keys on the real client address.
- [ ] Rotate every credential quoted in a chat, a ticket or a commit. Rotate first, then build.

See [.env.example](.env.example) for the full variable list and the reasoning behind each gate.

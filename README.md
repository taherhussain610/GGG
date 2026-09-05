# Neon Royale v2 — Hostinger Play-Money Casino

Hostinger-first PHP 8.3 + MySQL application. It is a virtual-credit/play-money platform:
no deposits, withdrawals, cash-out, payment processing, or real-money wagering.

## Reference-inspired UX
The supplied reference URLs were reviewed for information architecture: Casino, Sport,
Results, category tabs, search, live/top categories, game cards, compact navigation,
promotional blocks and responsive layouts. The implementation is original and does not
copy proprietary source code, branding, or protected assets.

## Included
- Compact responsive navigation and mobile bottom navigation
- Casino lobby + category tabs
- Slots, Roulette, Blackjack, Dice, Poker-style 5-card draw
- Simulated Sports page with markets and virtual picks
- Results page
- Daily bonus
- Jackpots
- XP/levels
- Leaderboard
- Profile + game history
- Admin dashboard: users, balances, bonuses, game statistics
- Demo provider dashboard with enable/disable controls, health checks and API logs
- Searchable fake provider catalog with categories, thumbnails and provider game IDs
- Provider launch abstraction, expiring sessions and idempotent virtual-wallet rounds
- HMAC-authenticated callback/webhook endpoints for sandbox events
- JSON API endpoints for balance, sports feed, results, leaderboard, bonus and health
- AJAX balance refresh and live-feed polling
- MySQL transactions and server-side game outcomes
- PHP sessions + password_hash/password_verify
- CSP/security headers and prepared statements
- Hostinger shared/web hosting compatible

## Hostinger setup
1. Create a MySQL database in hPanel.
2. Import `database.sql` with phpMyAdmin.
3. Edit `config/config.php` with the database credentials and `APP_KEY` (used to sign CSRF tokens if you are not setting it via the environment).
   Set `PROVIDER_WEBHOOK_SECRET` only if you want to exercise the signed demo callback.
4. Upload the contents of this folder into `public_html`.
5. Open `/register.php`.
6. Make your account admin:
   `UPDATE users SET is_admin=1 WHERE email='YOUR_EMAIL';`
7. Use HTTPS and keep PHP 8.3+ selected.

## Hostinger requirements
PHP 8.3+; PDO; pdo_mysql; sessions; JSON; random_int. No Composer or third-party PHP
extension is required.

## Sandbox provider layer

The provider layer deliberately supports only the built-in `demo` adapter. It makes no
external provider or payment calls and cannot be switched to production mode. The
Evolution and Pragmatic Play records are disabled configuration placeholders; they are
not integrations and do not imply affiliation or approval.

Flow:

`Casino lobby → catalog → provider/game ID → launch → expiring session → demo wallet round → transaction logs`

The provider tables in `database.sql` are:

- `providers` and `games`
- `game_sessions` and `game_rounds`
- `provider_transactions` and `wallet_transactions`
- `jackpots` and `provider_api_logs`

Player-facing routes:

| Route | Purpose |
| --- | --- |
| `/lobby.php` | Search and filter native and demo-provider games |
| `/provider/config.php` | Public, non-secret provider capabilities |
| `/provider/catalog.php` | Fake catalog JSON feed |
| `/provider/launch.php` | Authenticated, CSRF-protected game launch |
| `/provider/session.php` | Expiring local demo game session |
| `/provider/wallet.php` | Idempotent demo-credit round settlement |

Administrators can use `/provider/admin.php` to manage provider/game availability,
configuration placeholders, health state, sessions, jackpots, transactions and API logs.
No actual API credential is accepted by the dashboard.

`/provider/webhook.php` and its compatibility alias `/provider/callback.php` accept only
the demo events `health.ping`, `catalog.sync` and `session.closed`. Requests require
`X-Provider-Id`, `X-Provider-Timestamp` and `X-Provider-Signature`. The signature is
HMAC-SHA256 over `<timestamp>.<raw JSON body>` using a provider-specific key derived
from `PROVIDER_WEBHOOK_SECRET`; timestamps expire after five minutes and event IDs are
idempotent. Callback payloads and logs redact credential-like fields.

For an existing installation, back up the database and apply the provider-table section
of `database.sql` before opening the provider dashboard.

## Important deployment note
If you specifically want Next.js + Node.js + PostgreSQL + persistent WebSockets, use
Hostinger Web Apps/Cloud/VPS rather than ordinary shared PHP hosting. This app is
intentionally PHP + MySQL so it can run on a standard Hostinger PHP/MySQL plan.

## Production hardening
Add email verification, password reset, rate limiting, 2FA for admins, audit logs,
automated backups, monitoring and a formal security review. Real-money or external
provider activation additionally requires the relevant licensing, jurisdiction,
KYC/AML, responsible-gambling, certification, contract and provider-approval work;
none of that functionality is included here.

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
4. Upload the contents of this folder into `public_html`.
5. Open `/register.php`.
6. Make your account admin:
   `UPDATE users SET is_admin=1 WHERE email='YOUR_EMAIL';`
7. Use HTTPS and keep PHP 8.3+ selected.

## Hostinger requirements
PHP 8.3+; PDO; pdo_mysql; sessions; JSON; random_int. No Composer or third-party PHP
extension is required.

## Important deployment note
If you specifically want Next.js + Node.js + PostgreSQL + persistent WebSockets, use
Hostinger Web Apps/Cloud/VPS rather than ordinary shared PHP hosting. This app is
intentionally PHP + MySQL so it can run on a standard Hostinger PHP/MySQL plan.

## Production hardening
Add email verification, password reset, rate limiting, 2FA for admins, audit logs,
automated backups, monitoring and a formal security review.

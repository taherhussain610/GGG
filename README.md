# Neon Royale

Neon Royale is an original, mobile-first play-money casino built for PHP 8.3 and
MySQL hosting. Credits are virtual entertainment points only: there are no
deposits, purchases, withdrawals, prizes, or cash-out.

The supplied reference URLs informed the broad information architecture
(casino categories, sports, results, compact navigation, and responsive game
cards). No source code, branding, copy, or protected assets from those sites is
included.

## Features

- Responsive casino lobby with search and category filters
- Server-authoritative Slots, Roulette, Blackjack, Dice, and Five-card Draw
- Simulated sports events, picks, settlement, and public results
- Transactional virtual-credit wallet and immutable balance history
- Daily bonus, game jackpots, XP, levels, and leaderboard
- Registration, login, profile, and activity history
- Admin dashboard for users, credit adjustments, event settlement, and metrics
- JSON endpoints for balance, sports, results, leaderboard, rewards, and games
- CSRF protection, prepared statements, secure sessions, rate limits, and CSP
- Clean URL compatibility for `/casino/-1`, category routes, `/sports`, and
  `/results`

## Hostinger installation

1. In hPanel, create a MySQL database and database user.
2. Import `database.sql` into that database with phpMyAdmin.
3. Edit `config/config.php` or provide the corresponding environment variables:
   `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `APP_KEY`, `APP_URL`, and
   `APP_ENV`. Set `TRUST_PROXY_HEADERS=true` only when your trusted hosting
   proxy overwrites `X-Forwarded-Proto`.
4. Set `APP_KEY` to a unique random value of at least 32 characters.
5. Upload the repository contents to `public_html`.
6. Select PHP 8.3 or newer and enable PDO MySQL and sessions.
7. Register normally, then promote the first administrator in phpMyAdmin:

   ```sql
   UPDATE users SET is_admin = 1 WHERE email = 'you@example.com';
   ```

8. Set `APP_URL` to the full HTTPS origin and `APP_ENV=production`. Dynamic
   requests then enforce HTTPS; also enable Hostinger's force-HTTPS option so
   static assets are redirected at the web-server edge.

No Composer packages, Node process, cron job, or WebSocket server is required.

## Local validation

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/smoke.php
```

The app needs a MySQL database for browser and API testing. Copy the production
database first if you need representative staging data; never test against a
live wallet.

## Security and operations

- Keep `config/config.php` and database backups out of public downloads.
- Never use the example `APP_KEY` in production.
- Configure HTTPS before exposing login pages.
- Back up the database regularly and restrict phpMyAdmin access.
- Review admin audit entries after balance changes and event settlements.
- For a public launch, add email verification, password reset, 2FA for
  administrators, database-backed rate limiting, monitoring, and an independent
  security review.

## Compatibility note

This project deliberately uses plain PHP and MySQL so it runs on ordinary
Hostinger shared hosting. A persistent Node.js/WebSocket architecture instead
requires Hostinger Web Apps, Cloud hosting, or a VPS.

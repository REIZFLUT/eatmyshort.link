# EatMyShort.Link

**English** · [Deutsch](README.de.md)

Self-hosted short-link service for companies — like Bitly, but on your own server.
Built on a lean Symfony 8 core with SQLite, no Node build step and no background workers.

## Features

- **Any number of (sub)domains** – `go.company.com/trade-show`, `jobs.company.com/apprentice`; short codes are
  unique per domain, and the root page and unknown codes can be redirected per domain (optionally with the path
  appended).
- **Language-aware targets** – one short link, different target URLs depending on the browser language.
- **Tracking without cookies** – clicks per day, unique visitors, referrers, devices, browsers, operating systems,
  languages, optionally countries (local GeoIP database). No full IP addresses; bots are detected and never stored.
  Recording happens only after the redirect has been sent — no added latency.
- **Multiple users** – everyone manages their own links, admins see everything; admins grant domains per user.
  Invitations by e-mail link, password reset, login throttling.
- **Admin area can be restricted to IP addresses/networks** (IPv4/IPv6, CIDR) with lock-out protection.
- **Passkeys (WebAuthn)** for passwordless, phishing-resistant sign-in; **TOTP** with backup codes for password
  logins; mandatory 2FA for admins can be switched on.
- QR codes (SVG/PNG), CSV export, expiry date, active toggle, 301/302/307/308.
- **REST API** with personal tokens ([docs/API.md](docs/API.md)).
- Interface in German and English, light/dark, usable on mobile, custom logo and accent colour
  (default: Simpsons yellow `#fed90f`; text and font colours are calculated for WCAG contrast,
  the favicon picks up the colour).

## Installation

See **[docs/INSTALL.md](docs/INSTALL.md)** (PHP 8.4, nginx or Apache, SQLite).

In short:

```bash
composer install --no-dev -a
cp docs/examples/env.local.dist .env.local   # adjust the values
php bin/console doctrine:migrations:migrate -n
php bin/console asset-map:compile
php bin/console app:user:create admin@company.com "Admin" --admin
php bin/console app:domain:add go.company.com --default
```

## Console commands

| Command | Purpose |
|---|---|
| `app:user:create <email> [name] [--admin] [--invite]` | Create a user (prompts for a password or issues an invitation link) |
| `app:domain:add <host> [--root=URL] [--default]` | Add a short-link domain |
| `app:admin-ip [list\|allow\|remove\|clear] [cidr] [label]` | Manage the IP restriction for `/admin` (emergency access) |
| `app:clicks:prune [--days=N]` | Delete old clicks (cron) |
| `app:db:backup [target]` | Consistent copy of the SQLite database |

## Development

```bash
composer install
php bin/console doctrine:migrations:migrate -n
vendor/bin/phpunit                 # tests (separate SQLite test database)
vendor/bin/phpstan analyse         # level 8
vendor/bin/php-cs-fixer fix --dry-run
```

Architecture in brief:

- `src/EventListener/ShortLinkHostListener.php` – every host except `APP_ADMIN_HOSTS` goes straight to the
  `RedirectController`; app routes do not exist there.
- `src/Tracking/ClickRecorder.php` – writes clicks in `kernel.terminate`, after the response has been sent.
- `src/Security/` – IP restriction, API tokens, voters, encrypted TOTP secrets, passkey integration.
- `src/Doctrine/SqlitePragmaMiddleware.php` – WAL, busy timeout, foreign keys.

The previous version on the Continy framework lives under the tag `continy-final`;
what was carried over from it is documented in [docs/legacy-inventory.md](docs/legacy-inventory.md).

## License

GPL-3.0, see [LICENSE](LICENSE).
# EatMyShort.Link

[English](README.md) · **Deutsch**

Selbst gehosteter Kurzlink-Dienst für Unternehmen – wie Bitly, nur auf dem eigenen Server.
Gebaut auf einem schlanken Symfony-8-Kern mit SQLite, ohne Node-Build und ohne Hintergrunddienste.

## Funktionen

- **Beliebig viele (Sub)Domains** – `go.firma.de/messe`, `jobs.firma.de/azubi`; Kürzel sind pro Domain eindeutig,
  Startseite und unbekannte Kürzel lassen sich je Domain weiterleiten (optional mit angehängtem Pfad).
- **Sprachabhängige Ziele** – ein Kurzlink, je nach Browsersprache unterschiedliche Ziel-URLs.
- **Tracking ohne Cookies** – Klicks pro Tag, eindeutige Besucher, Herkunft, Geräte, Browser, Betriebssysteme,
  Sprachen, optional Länder (lokale GeoIP-Datenbank). Keine vollständigen IP-Adressen, Bots werden erkannt und
  gar nicht erst gespeichert. Erfasst wird erst nach dem Absenden der Weiterleitung – keine zusätzliche Wartezeit.
- **Mehrere Nutzer** – jeder verwaltet seine Links, Admins sehen alles; Admins geben Domains pro Nutzer frei.
  Einladung per E-Mail-Link, Passwort vergessen, Login-Drosselung.
- **Admin-Bereich auf IP-Adressen/Netze einschränkbar** (IPv4/IPv6, CIDR) mit Aussperr-Schutz.
- **Passkeys (WebAuthn)** für passwortlose, phishing-sichere Anmeldung; **TOTP** mit Backup-Codes für Passwort-Logins;
  2FA-Pflicht für Admins einschaltbar.
- **Lesezeichen** – die aktuelle Seite direkt aus der Lesezeichenleiste kürzen (mit oder ohne Wunschkürzel),
  optional anlegen und gleich weiterleiten; Teilen-Links auf jeder Link-Seite.
- **CSV-Import** – eine gewachsene YOURLS-Instanz oder eine Excel-Liste in einem Schritt übernehmen:
  erst Vorschau, YOURLS-Spalten (`url`, `keyword`, `title`) werden erkannt, Dubletten übersprungen.
- **Arbeitsliste** – nach Klicks, letztem Klick, Ziel oder Kürzel sortieren; mehrere Links auf einmal
  aktivieren, deaktivieren, löschen oder verschieben (nur Links, die man ändern darf).
- **Automatische Seitentitel** – beim Anlegen vom Ziel abgeholt (abschaltbar, mit SSRF-Schutz, damit der
  Server nie private Adressen aufruft).
- QR-Codes (SVG/PNG), CSV-Export, Ablaufdatum, Aktiv-Schalter, 301/302/307/308.
- **REST-API** mit persönlichen Tokens ([docs/API.md](docs/API.md)).
- Oberfläche auf Deutsch und Englisch, hell/dunkel, mobil nutzbar, eigenes Logo und Akzentfarbe
  (Standard: Simpsons-Gelb `#fed90f`; Schrift- und Textfarben werden für Kontrast nach WCAG berechnet,
  das Favicon übernimmt die Farbe).

## Installation

Siehe **[docs/INSTALL.md](docs/INSTALL.md)** (PHP 8.4, nginx oder Apache, SQLite).

Kurzfassung:

```bash
composer install --no-dev -a
cp .env.example .env                         # anpassen
php bin/console doctrine:migrations:migrate -n
php bin/console asset-map:compile
php bin/console app:user:create admin@firma.de "Admin" --admin
php bin/console app:domain:add go.firma.de --default
```

## Konsolenbefehle

| Befehl | Zweck |
|---|---|
| `app:user:create <email> [name] [--admin] [--invite]` | Nutzer anlegen (Passwort abfragen oder Einladungslink) |
| `app:domain:add <host> [--root=URL] [--default]` | Kurzdomain anlegen |
| `app:admin-ip [list\|allow\|remove\|clear] [cidr] [label]` | IP-Sperre für `/admin` verwalten (Notfallzugang) |
| `app:clicks:prune [--days=N]` | alte Klicks löschen (Cron) |
| `app:db:backup [ziel]` | konsistente Kopie der SQLite-Datenbank |

## Entwicklung

```bash
composer install
php bin/console doctrine:migrations:migrate -n
vendor/bin/phpunit                 # Tests (eigene SQLite-Testdatenbank)
vendor/bin/phpstan analyse         # Level 8
vendor/bin/php-cs-fixer fix --dry-run
```

Aufbau in Kürze:

- `src/EventListener/ShortLinkHostListener.php` – jeder Host außer `APP_ADMIN_HOSTS` geht direkt an den
  `RedirectController`; App-Routen existieren dort nicht.
- `src/Tracking/ClickRecorder.php` – schreibt Klicks in `kernel.terminate`, nach dem Senden der Antwort.
- `src/Security/` – IP-Sperre, API-Tokens, Voter, verschlüsselte TOTP-Schlüssel, Passkey-Anbindung.
- `src/Doctrine/SqlitePragmaMiddleware.php` – WAL, Busy-Timeout, Foreign Keys.

Die frühere Version auf dem Continy-Framework liegt unter dem Tag `continy-final`;
was daraus übernommen wurde, steht in [docs/legacy-inventory.md](docs/legacy-inventory.md).

## Lizenz

GPL-3.0, siehe [LICENSE](LICENSE).
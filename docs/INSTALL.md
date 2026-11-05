# Installation (klassisches PHP-Hosting)

EatMyShort.Link braucht keinen Datenbankserver, keinen Node-Build und keinen
Queue-Worker: PHP-FPM, ein Webserver und eine SQLite-Datei reichen.

## Voraussetzungen

- PHP **8.4** (oder 8.5) mit `ctype`, `iconv`, `intl`, `pdo_sqlite`, `sodium`, `mbstring`;
  empfohlen `apcu` und `opcache`, für PNG-QR-Codes `gd`
- Composer 2
- nginx oder Apache, TLS-Zertifikate (z. B. Let's Encrypt) für Admin-Host und alle Kurzdomains
- ausgehender SMTP-Zugang für Einladungen und Passwort-Links (optional; ohne Mailer zeigt
  die Oberfläche den Link zum Weitergeben an)

## Hosts planen

- **Admin-Host**, z. B. `links.firma.de`: Verwaltung, Login, API. Steht in `APP_ADMIN_HOSTS`
  (der erste Eintrag ist zugleich die Passkey-Domain, später nicht mehr ändern, sonst
  funktionieren registrierte Passkeys nicht mehr).
- **Kurzdomains**, z. B. `go.firma.de`, `jobs.firma.de`: werden in der Oberfläche unter
  *Domains* angelegt. Jeder Host, der nicht in `APP_ADMIN_HOSTS` steht, wird als Kurzdomain
  behandelt; unbekannte Hosts bekommen eine 404-Seite.

Alle Namen zeigen per DNS (A/AAAA oder CNAME) auf denselben Server und landen im selben vhost.

## Schritte

```bash
cd /var/www
git clone https://github.com/REIZFLUT/eatmyshort.link.git eatmyshort
cd eatmyshort
cp docs/examples/env.local.dist .env.local      # Werte anpassen, siehe unten
composer install --no-dev --optimize-autoloader --classmap-authoritative
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console asset-map:compile
php bin/console app:user:create admin@firma.de "Vorname Nachname" --admin
php bin/console app:domain:add go.firma.de --root=https://www.firma.de --default
```

`var/` (Cache, Logs, `var/data/eatmyshort.sqlite` samt `-wal`/`-shm`) muss für den
PHP-FPM-Nutzer beschreibbar sein:

```bash
chown -R www-data:www-data var
```

### `.env.local`

| Variable | Bedeutung |
|---|---|
| `APP_ENV=prod` | Produktivmodus |
| `APP_SECRET` | 32+ zufällige Zeichen (`php -r "echo bin2hex(random_bytes(16));"`). Verschlüsselt auch die TOTP-Schlüssel – **nicht nachträglich ändern**, sonst müssen alle Nutzer TOTP neu einrichten. |
| `APP_ADMIN_HOSTS` | Admin-Host(s), kommagetrennt |
| `DEFAULT_URI` | `https://<admin-host>` (Links in Konsolenausgaben) |
| `DATABASE_URL` | Standard: `sqlite:///%kernel.project_dir%/var/data/eatmyshort.sqlite` |
| `MAILER_DSN`, `MAILER_FROM` | z. B. `smtp://user:pass@smtp.firma.de:587` |
| `ADMIN_IP_ALLOWLIST` | IPs/Netze, die `/admin` **immer** öffnen dürfen (Aussperr-Schutz), kommagetrennt |
| `TRUSTED_PROXIES` | nur hinter Reverse-Proxy/Load-Balancer: dessen IPs. Sonst leer lassen – die IP-Sperre verlässt sich auf die richtige Client-IP. |
| `APP_TIMEZONE` | Zeitzone der Statistik, Standard `Europe/Berlin` |
| `GEOIP_DATABASE_PATH` | optional, Pfad zu `GeoLite2-Country.mmdb` (MaxMind, kostenloses Konto) oder `dbip-country-lite.mmdb` (DB-IP, CC BY 4.0) |
| `CLICK_RETENTION_DAYS` | Aufbewahrung der Klicks, Standard 730, `0` = unbegrenzt |

## Webserver

- nginx: [`docs/examples/nginx.conf`](examples/nginx.conf)
- Apache: `public/.htaccess` ist enthalten; vhost mit `DocumentRoot …/public` und `AllowOverride All`.

Beide schicken **alle** Hosts (Admin-Host und Kurzdomains) an `public/index.php`.
HTTP leitet auf HTTPS um.

## Cronjobs

```cron
# Klicks älter als CLICK_RETENTION_DAYS löschen
15 3 * * *  www-data  php /var/www/eatmyshort/bin/console app:clicks:prune --quiet
# Sicherung der SQLite-Datenbank (konsistent im laufenden Betrieb), 14 Tage behalten
30 3 * * *  www-data  php /var/www/eatmyshort/bin/console app:db:backup --quiet && find /var/www/eatmyshort/var/backup -name '*.sqlite' -mtime +14 -delete
```

## Updates

```bash
bin/deploy.sh
```

Holt den neuen Stand (`git pull --ff-only`), installiert Abhängigkeiten, führt
Migrationen aus, kompiliert Assets und leert den Cache. Vorher ein Backup:
`php bin/console app:db:backup`.

## Sicherheit im Betrieb

- **IP-Sperre**: *Admin → IP-Sperre*. Ohne Einträge ist `/admin` von überall erreichbar.
  Ausgesperrt? `php bin/console app:admin-ip allow <deine-ip>` oder `app:admin-ip clear`.
- **Zweiter Faktor**: *Admin → Einstellungen → Zweiten Faktor für Admins vorschreiben*.
  Passkeys gelten als vollwertiger zweiter Faktor.
- **Passkeys** brauchen HTTPS und funktionieren nur unter dem ersten Host aus `APP_ADMIN_HOSTS`.
- Kurzdomains setzen keine Cookies und kennen keine Login- oder Admin-Routen.

## Leistung

Gemessen auf einem kleinen Entwicklungsrechner (4 Kerne, 4 PHP-FPM-Worker, Xdebug geladen,
OPcache mit Zeitstempelprüfung): **gut 50 Weiterleitungen pro Sekunde inklusive Tracking**,
also rund 3.000 pro Minute; 3.000 parallele Aufrufe wurden ohne Sperrfehler vollständig gezählt.
Die Weiterleitung selbst braucht im Kernel rund 4 ms, das Tracking danach rund 20 ms – der
Besucher wartet darauf nicht.

Für Produktion:

- `opcache.validate_timestamps=0` (nach jedem Deploy PHP-FPM neu laden) und
  `opcache.preload=/var/www/eatmyshort/config/preload.php`, `opcache.preload_user=www-data`
- kein Xdebug auf dem Server
- `pm.max_children` passend zu Kernen und RAM (je Worker ca. 30–40 MB)
- APCu aktivieren (wird für Systemcaches automatisch genutzt)

## Wechsel auf MariaDB/MySQL oder PostgreSQL

SQLite im WAL-Modus reicht für typische Unternehmenslasten. Wer trotzdem einen
Datenbankserver will: `DATABASE_URL` umstellen und Migrationen ausführen
(`doctrine:migrations:migrate`). Die Abfragen sind portabel; `app:db:backup` funktioniert
dann nicht mehr, dafür die Werkzeuge der Datenbank nutzen.

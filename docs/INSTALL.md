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
cp .env.example .env                            # Werte anpassen, siehe unten
composer install --no-dev --optimize-autoloader --classmap-authoritative
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console asset-map:compile
php bin/console app:user:create admin@firma.de "Vorname Nachname" --admin
php bin/console app:domain:add go.firma.de --root=https://www.firma.de --default
```

`var/` (Cache, Logs in `var/log`, Sessions in `var/sessions`,
`var/data/eatmyshort.sqlite` samt `-wal`/`-shm`) muss für den PHP-FPM-Nutzer
gehören und beschreibbar sein:

```bash
mkdir -p var/log var/sessions var/data
chown -R www-data:www-data var
```

`var/log` und `var/sessions` müssen beschreibbar bleiben: Produktionslogs landen in
`var/log/prod.log` (rotierend, 14 Dateien), und ohne beschreibbares `var/sessions`
gehen Anmeldungen verloren.

## Konfiguration ohne .env im Repository

Im Repository liegt **keine** `.env`-Datei (`.env.local` und `.env.test` sind
git-ignoriert), sondern nur die Vorlage **`.env.example`**. Beim Start lädt die
Anwendung in dieser Reihenfolge:

1. `.env`, falls vorhanden – sonst ersatzweise `.env.local` (git-ignoriert);
2. im Testlauf ausschließlich `.env.test` (git-ignoriert).

`.env.example` wird **nie** automatisch geladen; sie ist nur eine Kopiervorlage.

**Variante 1 – echte Umgebungsvariablen (empfohlen für Produktion).** Im
PHP-FPM-Pool:

```ini
env[APP_ENV] = prod
env[APP_SECRET] = <32+ zufällige Zeichen>
env[APP_ADMIN_HOSTS] = links.firma.de
env[DEFAULT_URI] = https://links.firma.de
```

oder in nginx, z. B. `fastcgi_param APP_ENV prod;` (dann auch die übrigen Werte).
Reale Umgebungsvariablen haben Vorrang vor jeder `.env`-Datei.

**Variante 2 – `.env` aus der Vorlage (einfach, bleibt git-ignoriert).**

```bash
cp .env.example .env        # Werte anpassen; für Produktion APP_ENV=prod und APP_SECRET setzen
```

**Ohne beides startet die App nicht:** Fehlt eine dot-env-Datei und ist `APP_ENV`
nicht als echte Umgebungsvariable gesetzt, bricht der Start mit einer klaren
Meldung ab, statt still im Dev-Modus (mit Debug) zu laufen.

`.env` und `.env.local` dürfen **niemals** ins Repository. `bin/deploy.sh` liefert
keine Umgebungsvariablen mit – sie bleiben im FPM-Pool bzw. in einer nicht
versionierten `.env`-Datei auf dem Server.

### `.env.local`

| Variable | Bedeutung |
|---|---|
| `APP_ENV=prod` | Produktivmodus |
| `APP_SECRET` | 32+ zufällige Zeichen (`php -r "echo bin2hex(random_bytes(16));"`). Verschlüsselt auch die TOTP-Schlüssel – **nicht nachträglich ändern**, sonst müssen alle Nutzer TOTP neu einrichten. Wird beim Start in `prod` geprüft: leer, zu kurz oder ein Platzhalter aus der Vorlage bricht den Boot ab (auch bei Konsolenbefehlen). |
| `APP_ADMIN_HOSTS` | Admin-Host(s), kommagetrennt |
| `DEFAULT_URI` | `https://<admin-host>` (Links in Konsolenausgaben) |
| `DATABASE_URL` | Standard: `sqlite:///%kernel.project_dir%/var/data/eatmyshort.sqlite` |
| `MAILER_DSN`, `MAILER_FROM` | z. B. `smtp://user:pass@smtp.firma.de:587` |
| `ADMIN_IP_ALLOWLIST` | IPs/Netze, die `/admin` **immer** öffnen dürfen (Aussperr-Schutz), kommagetrennt |
| `TRUSTED_PROXIES` | nur hinter Reverse-Proxy/Load-Balancer: **nur dessen IPs** (z. B. `10.0.0.5,10.0.0.6`), niemals `REMOTE_ADDR`. Sonst glaubt die App jedem Client-Header und die IP-Sperre ist mit einem gefälschten `X-Forwarded-For` umgehbar. Leer = keine Proxys. |
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

`app:db:backup` sichert **nur die Datenbank**. `.env.local` gehört in ein separates,
verschlüsseltes Backup, insbesondere `APP_SECRET`: Daraus werden die TOTP-Schlüssel
der Nutzer abgeleitet. Ohne diesen Wert sind die Seeds nach einem Serververlust
unwiederbringlich.

## Updates

```bash
bin/deploy.sh --dry-run   # zeigt nur die Schritte, ändert nichts
bin/deploy.sh
```

Das Skript prüft die Voraussetzungen, schreibt ein Datenbank-Backup
(`SKIP_BACKUP=1` überspringt es), aktiviert den Wartungsmodus (`var/maintenance`),
holt den neuen Stand (`git pull --ff-only`), installiert die Abhängigkeiten ohne
Dev-Pakete, führt Migrationen aus, kompiliert die Assets, leert und wärmt den
Prod-Cache, lädt PHP-FPM neu und prüft danach `GET /healthz`. Schlägt ein Schritt
zwischen Pull und FPM-Reload fehl, wird der alte Commit wiederhergestellt
(`ALLOW_ROLLBACK=0` deaktiviert das) und der Wartungsmodus beendet.

Der FPM-Reload ist Pflicht, weil unten `opcache.validate_timestamps=0` empfohlen
wird: ohne Reload läuft der alte Code gegen den neuen Container. Das Skript erkennt
`php*-fpm`-Units per systemd und fällt sonst auf `php-fpm -USR2` zurück; mit
`PHP_FPM_RELOAD='systemctl reload php8.4-fpm'` lässt sich der Befehl fest vorgeben.
Der Healthcheck nutzt `HEALTH_URL` oder leitet die URL aus `DEFAULT_URI` ab
(Standard `http://127.0.0.1/healthz`).

Release-Verzeichnisse mit symlink-Umschaltung sind die sauberere Variante: Der
Wechsel ist atomar und ein Rollback braucht kein `git reset`. Dieses Projekt bleibt
beim In-Place-Layout, weil das Hosting so eingerichtet ist.

## Sicherheit im Betrieb

- **IP-Sperre**: *Admin → IP-Sperre*. Ohne Einträge ist `/admin` von überall erreichbar.
  Ausgesperrt? `php bin/console app:admin-ip allow <deine-ip>` oder `app:admin-ip clear`.
- **Zweiter Faktor**: *Admin → Einstellungen → Zweiten Faktor für Admins vorschreiben*.
  Passkeys gelten als vollwertiger zweiter Faktor.
- **Passkeys** brauchen HTTPS und funktionieren nur unter dem ersten Host aus `APP_ADMIN_HOSTS`.
- Kurzdomains setzen keine Cookies und kennen keine Login- oder Admin-Routen.
- **`.env.local` separat sichern**: Die Datei enthält `APP_SECRET`, aus dem die
  TOTP-Schlüssel abgeleitet werden. `app:db:backup` sichert sie nicht mit; ohne eine
  verschlüsselte Kopie sind die Seeds nach einem Serververlust unwiederbringlich.
- **`/healthz`** ist ohne Login erreichbar und meldet nur `{"status":"ok"}` bzw. bei
  Datenbankfehlern `{"status":"error"}` mit HTTP 503 – ohne interne Details.

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
Datenbankserver will, muss mehr tun als `DATABASE_URL` umzustellen: Die mitgelieferten
Migrationen sind reines SQLite-DDL (`AUTOINCREMENT`, `CLOB` und Ähnliches) und laufen
auf MariaDB/MySQL oder PostgreSQL nicht. Nötig sind ein eigener Migrationssatz bzw.
eine Portierung des Schemas und ein Test der Abfragen auf der Zieldatenbank.
`app:db:backup` funktioniert danach nicht mehr (es unterstützt nur SQLite); Backups
laufen über die Werkzeuge der Datenbank.

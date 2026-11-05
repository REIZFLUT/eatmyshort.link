#!/usr/bin/env bash
#
# Deployment fuer EatMyShort.Link auf klassischem PHP-FPM-Hosting.
#
# Ablauf: Vorpruefungen -> Datenbank-Backup -> Wartungsmodus -> git pull ->
# composer install -> Migrationen -> Assets -> Cache -> PHP-FPM-Reload ->
# Wartungsmodus aus -> Healthcheck.
#
# Schlaegt ein Schritt zwischen Pull und FPM-Reload fehl, wird der alte Commit
# wiederhergestellt (sofern ALLOW_ROLLBACK nicht 0 ist). Der Wartungsmodus wird
# in jedem Fehlerfall beendet, damit die Seite nicht dauerhaft gesperrt bleibt.
#
# Optionen:
#   --dry-run            zeigt nur die Schritte, aendert nichts
#   -h, --help           diese Hilfe
#
# Umgebungsvariablen:
#   SKIP_BACKUP=1        kein Datenbank-Backup vor dem Deploy
#   ALLOW_ROLLBACK=0     bei Fehlern nicht auf den alten Commit zurueckgehen
#   PHP_FPM_RELOAD='...' eigener Befehl zum Neuladen von PHP-FPM
#   HEALTHCHECK=0        Healthcheck am Ende ueberspringen
#   HEALTH_URL=...       Default: DEFAULT_URI aus .env.local + /healthz,
#                        sonst http://127.0.0.1/healthz
#   HEALTH_RETRIES=10    Versuche (Default 10), HEALTH_RETRY_DELAY=2 Sekunden
#
# Release-Verzeichnisse mit symlink-Umschaltung sind die sauberere Variante
# (atomarer Wechsel, sofortiger Rollback ohne git reset). Dieses Skript bleibt
# bewusst beim vorhandenen In-Place-Layout.

set -Eeuo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

DRY_RUN=0
ROLLBACK_ARMED=0
OLD_COMMIT=""

log() { printf '\n==> %s\n' "$*"; }
info() { printf '    %s\n' "$*"; }
fail() {
    printf 'FEHLER: %s\n' "$*" >&2
    exit 1
}

# Fuehrt einen Befehl aus oder zeigt ihn im Dry-Run nur an.
run() {
    if ((DRY_RUN)); then
        printf '    [dry-run] %s\n' "$*"
        return 0
    fi
    printf '    $ %s\n' "$*"
    "$@"
}

usage() {
    cat <<'EOF'
Deployment fuer EatMyShort.Link (in-place).

Aufruf: bin/deploy.sh [--dry-run]

  --dry-run   zeigt nur die Schritte, aendert nichts
  -h, --help  diese Hilfe

Umgebung:
  SKIP_BACKUP=1         kein Datenbank-Backup vor dem Deploy
  ALLOW_ROLLBACK=0      bei Fehlern nicht auf den alten Commit zurueckgehen
  PHP_FPM_RELOAD='...'  eigener Befehl zum Neuladen von PHP-FPM
  HEALTHCHECK=0         Healthcheck am Ende ueberspringen
  HEALTH_URL=...        Default: DEFAULT_URI aus .env.local + /healthz,
                        sonst http://127.0.0.1/healthz
  HEALTH_RETRIES=10     Versuche, HEALTH_RETRY_DELAY=2 Sekunden
EOF
}

while (($# > 0)); do
    case "$1" in
        --dry-run) DRY_RUN=1 ;;
        -h | --help)
            usage
            exit 0
            ;;
        *) fail "Unbekannte Option: $1 (erlaubt: --dry-run)" ;;
    esac
    shift
done

# ---------------------------------------------------------------------------
# Fehlerbehandlung: Wartungsmodus beenden, Code zurueckrollen, FPM neu laden.
# ---------------------------------------------------------------------------
on_error() {
    local status=$?
    trap - ERR
    set +e

    printf '\nFEHLER: Deployment abgebrochen (Exit-Code %s).\n' "$status" >&2

    if [[ -f var/maintenance ]]; then
        rm -f var/maintenance && printf '    Wartungsmodus beendet.\n'
    fi

    if ((ROLLBACK_ARMED)) && [[ "${ALLOW_ROLLBACK:-1}" != "0" ]] && [[ -n "$OLD_COMMIT" ]]; then
        printf '    Rollback auf %s ...\n' "$OLD_COMMIT"
        if git reset --hard "$OLD_COMMIT"; then
            printf '    Code auf den alten Commit zurueckgesetzt.\n'
            reload_fpm || printf '    WARNUNG: PHP-FPM liess sich beim Rollback nicht neu laden.\n'
        else
            printf '    WARNUNG: git reset --hard ist fehlgeschlagen; bitte manuell eingreifen.\n' >&2
        fi
    elif ((ROLLBACK_ARMED)); then
        printf '    Rollback deaktiviert (ALLOW_ROLLBACK=0); bitte Stand und Dienst manuell pruefen.\n' >&2
    fi

    exit "$status"
}
trap on_error ERR

# ---------------------------------------------------------------------------
# PHP-FPM neu laden. Reihenfolge: PHP_FPM_RELOAD, systemd-Unit php*-fpm,
# php-fpm-Binary mit -USR2. Ohne Reload laeuft bei opcache.validate_timestamps=0
# weiterhin der alte Code gegen den neuen Container.
# ---------------------------------------------------------------------------
reload_fpm() {
    if [[ -n "${PHP_FPM_RELOAD:-}" ]]; then
        run bash -c "$PHP_FPM_RELOAD" || return 1
        return 0
    fi

    if ((DRY_RUN)); then
        info '[dry-run] PHP-FPM neu laden (systemctl reload php*-fpm oder php-fpm -USR2)'
        return 0
    fi

    local unit=""
    if command -v systemctl >/dev/null 2>&1; then
        unit="$(systemctl list-units --type=service --plain --no-legend 'php*-fpm.service' 2>/dev/null | awk 'NR == 1 {print $1}')" || true
    fi
    if [[ -n "$unit" ]]; then
        if ! systemctl reload "$unit"; then
            printf 'FEHLER: PHP-FPM (%s) liess sich nicht neu laden.\n' "$unit" >&2
            return 1
        fi
        info "systemctl reload $unit"
        return 0
    fi

    local candidate
    for candidate in php-fpm php-fpm8.4 php-fpm8.3 /usr/sbin/php-fpm /usr/sbin/php-fpm8.4; do
        if command -v "$candidate" >/dev/null 2>&1; then
            if ! "$candidate" -USR2; then
                printf 'FEHLER: %s -USR2 ist fehlgeschlagen.\n' "$candidate" >&2
                return 1
            fi
            info "$candidate -USR2"
            return 0
        fi
    done

    printf 'FEHLER: Kein PHP-FPM gefunden. PHP_FPM_RELOAD setzen (z. B. "systemctl reload php8.4-fpm").\n' >&2
    return 1
}

# ---------------------------------------------------------------------------
# Vorpruefungen
# ---------------------------------------------------------------------------
log 'Vorpruefungen'
command -v git >/dev/null 2>&1 || fail 'git ist nicht installiert.'
command -v composer >/dev/null 2>&1 || fail 'composer ist nicht installiert.'
command -v php >/dev/null 2>&1 || fail 'php ist nicht installiert.'
git rev-parse --is-inside-work-tree >/dev/null 2>&1 || fail "$PROJECT_DIR ist kein Git-Repository."
[[ -w "$PROJECT_DIR" ]] || fail "$PROJECT_DIR ist fuer $(id -un) nicht beschreibbar."
[[ -d var && -w var ]] || fail 'var/ fehlt oder ist nicht beschreibbar (chown auf den PHP-FPM-Nutzer).'
if ! git diff --quiet || ! git diff --cached --quiet; then
    fail 'Das Repository hat lokale Aenderungen. Bitte bereinigen; deploy.sh ueberschreibt sonst nichts, aber git pull --ff-only scheitert.'
fi

if [[ "${HEALTHCHECK:-1}" != "0" && -z "${HEALTH_URL:-}" ]]; then
    command -v curl >/dev/null 2>&1 || fail 'curl fehlt, wird fuer den Healthcheck gebraucht (oder HEALTHCHECK=0 setzen).'
fi

# ---------------------------------------------------------------------------
# 1) Backup
# ---------------------------------------------------------------------------
if [[ "${SKIP_BACKUP:-0}" == "1" ]]; then
    log 'Backup uebersprungen (SKIP_BACKUP=1)'
else
    log 'Datenbank-Backup'
    run php bin/console app:db:backup
fi

# ---------------------------------------------------------------------------
# 2) Wartungsmodus
# ---------------------------------------------------------------------------
log 'Wartungsmodus aktivieren'
if ((DRY_RUN)); then
    info '[dry-run] var/maintenance anlegen'
else
    mkdir -p var
    : >var/maintenance
fi

# ---------------------------------------------------------------------------
# 3) Code aktualisieren
# ---------------------------------------------------------------------------
OLD_COMMIT="$(git rev-parse HEAD)"
ROLLBACK_ARMED=1

log "Code aktualisieren (git pull --ff-only, bisher $OLD_COMMIT)"
if ((DRY_RUN)); then
    info '[dry-run] git pull --ff-only'
else
    git pull --ff-only
    info "neuer Stand: $(git rev-parse --short HEAD)"
fi

# ---------------------------------------------------------------------------
# 4) Abhaengigkeiten
# ---------------------------------------------------------------------------
log 'Composer-Abhaengigkeiten installieren'
run composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction

# ---------------------------------------------------------------------------
# 5) Datenbankschema
# ---------------------------------------------------------------------------
log 'Migrationen ausfuehren'
run php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# ---------------------------------------------------------------------------
# 6) Assets
# ---------------------------------------------------------------------------
log 'Asset-Map kompilieren'
run php bin/console asset-map:compile

# ---------------------------------------------------------------------------
# 7) Cache
# ---------------------------------------------------------------------------
log 'Cache leeren und aufwaermen'
run php bin/console cache:clear --env=prod
run php bin/console cache:warmup --env=prod

# ---------------------------------------------------------------------------
# 8) PHP-FPM neu laden (sonst laeuft der alte OPcache-Inhalt weiter)
# ---------------------------------------------------------------------------
log 'PHP-FPM neu laden'
reload_fpm

# ---------------------------------------------------------------------------
# 9) Wartungsmodus beenden
# ---------------------------------------------------------------------------
log 'Wartungsmodus beenden'
if ((DRY_RUN)); then
    info '[dry-run] var/maintenance entfernen'
else
    rm -f var/maintenance
fi
ROLLBACK_ARMED=0

# ---------------------------------------------------------------------------
# 10) Healthcheck (Fehler rollt nicht zurueck: der Code ist bereits live)
# ---------------------------------------------------------------------------
health_url() {
    if [[ -n "${HEALTH_URL:-}" ]]; then
        printf '%s' "$HEALTH_URL"
        return
    fi

    local uri=""
    if [[ -f .env.local ]]; then
        uri="$(grep -E '^[[:space:]]*DEFAULT_URI=' .env.local | tail -n 1 | cut -d= -f2- | tr -d '"' | tr -d "'" || true)"
    fi
    if [[ -n "$uri" ]]; then
        printf '%s/healthz' "${uri%/}"
    else
        printf 'http://127.0.0.1/healthz'
    fi
}

if [[ "${HEALTHCHECK:-1}" == "0" ]]; then
    log 'Healthcheck uebersprungen (HEALTHCHECK=0)'
else
    url="$(health_url)"
    log "Healthcheck: $url"
    if ((DRY_RUN)); then
        info "[dry-run] curl -fsS $url (mit Retry)"
    else
        retries="${HEALTH_RETRIES:-10}"
        delay="${HEALTH_RETRY_DELAY:-2}"
        ok=0
        for ((attempt = 1; attempt <= retries; attempt++)); do
            if curl -fsS --max-time 5 -o /dev/null "$url"; then
                info "OK nach Versuch $attempt"
                ok=1
                break
            fi
            info "Versuch $attempt/$retries fehlgeschlagen, warte ${delay}s"
            sleep "$delay"
        done
        [[ "$ok" == "1" ]] || fail "Healthcheck $url ist nach $retries Versuchen fehlgeschlagen."
    fi
fi

log "Deployment abgeschlossen: $(git rev-parse --short HEAD)"
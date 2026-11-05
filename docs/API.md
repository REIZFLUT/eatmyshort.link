# REST-API v1

Basis-URL: `https://<admin-host>/api/v1` · Format: JSON · Authentifizierung:
persönliches Token aus **Profil → API** als `Authorization: Bearer emsl_…`.

Ein Token handelt mit den Rechten seines Nutzers: eigene Links (Admins: alle),
nur freigegebene Domains (Admins: alle aktiven). Limit: 120 Anfragen pro Minute
und Nutzer (`429` mit `Retry-After`).

## Endpunkte

| Methode | Pfad | Zweck |
|---|---|---|
| GET | `/domains` | Domains, die das Token verwenden darf |
| GET | `/links?q=&domain=&page=` | Links suchen (50 pro Seite) |
| POST | `/links` | Link anlegen |
| GET | `/links/{id}` | Link lesen |
| PATCH | `/links/{id}` | Link ändern (nur übergebene Felder) |
| DELETE | `/links/{id}` | Link löschen (`204`) |
| GET | `/links/{id}/stats?days=7\|30\|90\|365` | Statistik |

## Link-Felder

| Feld | Typ | Hinweis |
|---|---|---|
| `target_url` | string | Pflicht beim Anlegen, nur http(s), nicht auf eigene Domains |
| `domain` | string\|int | Hostname oder ID; ohne Angabe die Standard-Domain des Nutzers |
| `code` | string | Wunschkürzel; leer/fehlend = zufällig. Kleinbuchstaben, Ziffern, `-`, `_` |
| `title` | string\|null | interne Bezeichnung |
| `redirect_type` | int | 302 (Standard), 301, 307, 308 |
| `active` | bool | |
| `expires_at` | string\|null | ISO 8601 |
| `localisations` | object | `{"en": "https://…", "de-at": "https://…"}` – ersetzt alle bisherigen |

Antwort (`data`): zusätzlich `id`, `short_url`, `clicks`, `last_click_at`,
`owner`, `created_at`, `updated_at`.

## Beispiele

```bash
curl -X POST https://links.example.com/api/v1/links \
  -H "Authorization: Bearer emsl_…" -H "Content-Type: application/json" \
  -d '{"target_url": "https://www.example.com/messe", "code": "messe", "domain": "go.example.com"}'
```

```json
{"data": {"id": 12, "short_url": "https://go.example.com/messe", "code": "messe", "target_url": "https://www.example.com/messe", "redirect_type": 302, "active": true, "localisations": {}, "clicks": 0, "…": "…"}}
```

## Fehler

- `401` – Token fehlt, ist ungültig, abgelaufen oder der Nutzer gesperrt
- `403` – Link gehört jemand anderem
- `400` – Body ist kein JSON-Objekt
- `422` – Validierung: `{"error": "validation_failed", "violations": [{"field": "code", "message": "…"}]}`

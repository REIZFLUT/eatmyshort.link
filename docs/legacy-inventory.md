# Bestandsaufnahme: Continy-Version (Tag `continy-final`)

Stand des Altcodes vor dem Symfony-Neuaufbau, als Feature-Referenz. Es werden
**keine Daten übernommen** (Neustart).

## Was es gab

| Bereich | Altcode | Neu |
|---|---|---|
| Kurzlinks | `shortlinks` (`from_path`, `to_url`, `usr`), Pfad nur `[a-z0-9_-]`, alle Nutzer sehen alle Links | Links pro Domain, Besitzer + Admin-Sicht, Zufallskürzel, Ablaufdatum, Aktiv-Schalter, Weiterleitungstyp |
| Sprachabhängige Ziele | `localisations` (`sl_id`, `lng`, `to_url`), Auswahl über Accept-Language; Bearbeiten war **nicht fertig** (leere Controller) | `LinkLocalisation`, vollständig im Formular und in der API |
| Unbekannte Pfade | Einstellung `forwardAll` + `defaultDomain` (Pfad wird angehängt) | pro Domain: „Unbekannte Kürzel weiterleiten an“ + „Pfad anhängen“ |
| Startseite der Domain | Weiterleitung auf `defaultDomain` | pro Domain „Startseite weiterleiten an“ |
| Zähler | `counter` mit Browser, Version, Plattform, Gerät, Sprache, **voller IP** und Land über externen Dienst (geoplugin, IP wurde verschickt) | `Click` ohne volle IP (/24 bzw. /48), Tages-Hash für eindeutige Besucher, Land lokal per GeoLite2, Bot-Erkennung |
| Nutzer | Rollen `user`, `manager`, `admin`; Nutzerverwaltung; Passwort vergessen per Mail; Login-Sperren für Nutzer und IP | `ROLE_USER`/`ROLE_ADMIN`, Einladung per Link, Passwort-Reset, Login-Throttling, TOTP, Passkeys |
| Einstellungen | Tabelle `config` (Schlüssel/Wert): `title`, `topLogo`, `theme`, `useCounter`, `counterCountryOnForward`, `resultsPerPage`, `availableLocalisationLanguages`, `shortDomain`, `defaultDomain`, `forwardAll`, `timezone` | `Settings` (Titel, Logo, Akzentfarbe, Kürzel-Länge, Standard-Weiterleitung, Tracking, Land, 2FA-Pflicht); Zeitzone über `APP_TIMEZONE` |
| API | `ApiController::requestToken` (Token per E-Mail/Passwort) – **nicht geroutet** | REST-API v1 mit persönlichen Tokens |
| Sprache der Oberfläche | de/en | de/en (Browser oder Profil) |
| Logo | `top_logo.svg`, Favicon | übernommen (Wortmarke als Inline-SVG, Farbe per CSS) |

## Bewusst nicht übernommen

- Einzeldomain-Modell (`shortDomain`): ersetzt durch beliebig viele (Sub)Domains.
- Externe Geo-Abfrage mit voller IP-Adresse (Datenschutz).
- Speicherung voller IP-Adressen.

# Plugins

A plugin is a self-contained directory here (`plugins/<name>/`) that is loaded when it is
named in [`config/plugins.yaml`](../config/plugins.yaml). Nothing else is loaded: a
directory that is not in the whitelist is ignored entirely.

```
plugins/
└── utm-tagging/
    ├── plugin.yaml            # name, version, description, optional requires.api
    ├── services.yaml          # optional: container services (autowire/autoconfigure)
    ├── config/
    │   └── routes.yaml        # optional: routes the plugin adds
    ├── README.md              # optional but recommended
    └── src/                   # PHP classes, namespace Plugin\UtmTagging
        └── ...
```

- **Directory name** = plugin name = whitelist key. `[a-z0-9-]`, 3-40 characters.
- **Namespace** is derived from the directory name: `utm-tagging` → `Plugin\UtmTagging`.
  Classes resolve through the app's Composer class loader; no `composer dump-autoload`
  is needed.
- **plugin.yaml** carries `name`, `version`, `description` and optionally
  `requires: {api: ">=1.0"}`. The app refuses to boot when a required API version is not
  provided.
- **services.yaml** and **config/routes.yaml** are imported into the container and the
  router as-is. The plugin is responsible for access control on its own routes.

Plugins are trusted code: a loaded plugin runs with the same rights as the application
(database, filesystem, network) and is not sandboxed.

## Settings

The `settings:` block of a whitelist entry is free-form and gives the plugin the operator's
configuration. The kernel exposes it as a **container parameter** the plugin can inject from
its own `services.yaml`:

```
app.plugins.<name>.settings
```

For `utm-tagging` that parameter is `app.plugins.utm-tagging.settings`:

```yaml
# plugins/utm-tagging/services.yaml
services:
    Plugin\UtmTagging\UtmTagger:
        arguments:
            $settings: '%app.plugins.utm-tagging.settings%'
```

The parameter is set for every **enabled** plugin (a disabled plugin never has its services
imported, so it never needs one). Every key is optional: a plugin owns its settings shape
and must cope with missing keys by using defaults, so an enabled but unconfigured plugin
does nothing instead of failing.

## Commands

Run `bin/console app:plugin:list` to see what is loaded and `bin/console app:plugin:validate`
to check the whitelist and every plugin directory before deploying. The validator exits with
code 1 on errors and 0 when there are only warnings.

## Shipped examples

Two working examples live in this directory, both disabled by default:

| Plugin | Purpose | Settings |
|---|---|---|
| `utm-tagging` | appends campaign parameters to redirect targets | `source`, `medium`, `campaign`, `content`, `term`, `overwrite` |
| `click-webhook` | reports recorded clicks as a signed JSON webhook | `url`, `timeout`, `secret`, `headers` |

Each ships its own `README.md` with its purpose, its settings and how to enable it.

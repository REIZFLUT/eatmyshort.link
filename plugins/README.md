# Plugins

A plugin is a self-contained directory here (`plugins/<name>/`) that is loaded when it is
named in [`config/plugins.yaml`](../config/plugins.yaml). Nothing else is loaded: a
directory that is not in the whitelist is ignored entirely.

```
plugins/
└── example-utm/
    ├── plugin.yaml            # name, version, description, optional requires.api
    ├── services.yaml          # optional: container services (autowire/autoconfigure)
    ├── config/
    │   └── routes.yaml        # optional: routes the plugin adds
    └── src/                   # PHP classes, namespace Plugin\ExampleUtm
        └── ...
```

- **Directory name** = plugin name = whitelist key. `[a-z0-9-]`, 3-40 characters.
- **Namespace** is derived from the directory name: `example-utm` → `Plugin\ExampleUtm`.
  Classes resolve through the app's Composer class loader; no `composer dump-autoload`
  is needed.
- **plugin.yaml** carries `name`, `version`, `description` and optionally
  `requires: {api: ">=1.0"}`. The app refuses to boot when a required API version is not
  provided.
- **services.yaml** and **config/routes.yaml** are imported into the container and the
  router as-is. The plugin is responsible for access control on its own routes.

Plugins are trusted code: a loaded plugin runs with the same rights as the application
(database, filesystem, network) and is not sandboxed.

Run `bin/console app:plugin:list` to see what is loaded and `bin/console app:plugin:validate`
to check the whitelist and every plugin directory before deploying.

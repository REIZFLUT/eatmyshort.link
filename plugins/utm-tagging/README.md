# utm-tagging

Appends campaign parameters (`utm_source`, `utm_medium`, `utm_campaign`, `utm_content`,
`utm_term`) to a short link's redirect target. This is the classic use case for a company
short-link service: marketing writes `go.example.com/newsletter`, the plugin turns the
stored target into a trackable URL, and analytics attributes the visit to the campaign.

It listens to `App\Event\Redirect\RedirectResolvingEvent` and rewrites the target with
`$event->setTarget()`; the redirect hot path does string work only, so it adds no queries and
no network round trip.

## Settings

All settings are optional. A plugin that is enabled but not configured does nothing.

| Key | Type | Meaning |
|---|---|---|
| `source` | string | `utm_source` - the referrer (e.g. `newsletter`) |
| `medium` | string | `utm_medium` - the channel (e.g. `email`) |
| `campaign` | string | `utm_campaign` - the campaign name |
| `content` | string | `utm_content` - the variant / ad |
| `term` | string | `utm_term` - the paid keyword |
| `overwrite` | bool (default `false`) | When `false` an existing parameter of the same name is left alone; when `true` its value is replaced in place |

Behaviour details:

- Only `http`/`https` targets are touched; a `mailto:` or any other target is left as is.
- Missing keys and non-string values are ignored - no configuration throws nothing.
- The URL path, the fragment and the order of existing parameters are left untouched; new
  parameters are appended.
- An unparsable target is returned unchanged (the tagger never throws).

## Enabling it

Add it to [`config/plugins.yaml`](../../config/plugins.yaml):

```yaml
plugins:
    utm-tagging:
        enabled: true
        settings:
            source: newsletter
            medium: email
            campaign: spring-launch
```

Then reload the container (`php bin/console cache:clear --env=<env>`). The settings reach the
plugin as the container parameter `app.plugins.utm-tagging.settings`; `services.yaml` injects
that parameter into `UtmTagger`. See [`plugins/README.md`](../README.md) for the contract.

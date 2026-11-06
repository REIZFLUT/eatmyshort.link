<?php

declare(strict_types=1);

namespace App\Plugin;

/**
 * A plugin is misconfigured in a way the operator has to fix (missing directory,
 * unparsable plugin.yaml, unsupported app version, name mismatch).
 *
 * Raised while the container is built: a broken plugin stops the boot instead of
 * half-loading. Fix config/plugins.yaml or the plugin, then clear the cache.
 *
 * @api
 *
 * @since 1.0
 */
final class PluginException extends \RuntimeException
{
}

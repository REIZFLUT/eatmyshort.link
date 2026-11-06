<?php

declare(strict_types=1);

namespace App\Plugin;

/**
 * The contract version plugins are written against.
 *
 * It moves when the event contract changes in a way plugins can notice: a new event or
 * a new optional method bumps the minor version, a breaking change the major one. A
 * plugin declares what it needs in its plugin.yaml (requires.api) and the app refuses to
 * load it when that cannot be satisfied - better a loud failure at boot than a plugin
 * that silently stops doing its job.
 *
 * Stability promise: within a major version, events and their documented methods stay
 * backwards compatible; new events are additive.
 *
 * @api
 *
 * @since 1.0
 */
final class PluginApi
{
    public const string VERSION = '1.0';

    private function __construct()
    {
    }
}

<?php

declare(strict_types=1);

namespace Plugin\Probe;

/**
 * Proves the settings-parameter contract: it is autowired from the plugin's own
 * services.yaml with '%app.plugins.probe.settings%' - the settings the test whitelist
 * (tests/Fixtures/plugins.yaml) gives the probe plugin.
 */
final readonly class ProbeSettings
{
    /** @param array<string, mixed> $settings */
    public function __construct(public array $settings)
    {
    }
}

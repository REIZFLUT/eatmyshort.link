<?php

declare(strict_types=1);

namespace Plugin\Probe;

/**
 * Shared counter so the tests can observe that plugin services are wired and that the
 * plugin's event listener actually runs.
 */
final class ProbeRecorder
{
    private int $calls = 0;

    public function record(): void
    {
        ++$this->calls;
    }

    public function calls(): int
    {
        return $this->calls;
    }
}

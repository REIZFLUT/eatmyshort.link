<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Psr\Log\AbstractLogger;

/**
 * Records everything a service logs so a test can assert on it.
 *
 * Installed as the "logger" service for a single test through
 * EventTestCase::captureLogs(); it has to be in place before the service that logs is
 * first instantiated, which in a functional test is the first request of that test.
 */
final class CapturingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string, context: array<string, mixed>}> */
    private array $records = [];

    /**
     * @param array<string, mixed> $context
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => (string) $level, 'message' => (string) $message, 'context' => $context];
    }

    public function hasRecord(string $level, string $needle): bool
    {
        foreach ($this->records as $record) {
            if ($record['level'] === $level && str_contains($record['message'], $needle)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{level: string, message: string, context: array<string, mixed>}> */
    public function getRecords(): array
    {
        return $this->records;
    }
}

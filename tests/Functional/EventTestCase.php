<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Support\CapturingLogger;
use App\Tests\Support\EventTestState;

/**
 * Base class for the event-contract tests: wires up the test listeners and guarantees
 * their state does not leak into other tests.
 */
abstract class EventTestCase extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        EventTestState::reset();
    }

    protected function tearDown(): void
    {
        EventTestState::reset();
        parent::tearDown();
    }

    /**
     * Redirects every log record to a capturing logger the test can inspect.
     *
     * Must run before the logging service is first used in the test (i.e. before the first
     * request) so the service under test receives the capturing instance.
     */
    protected function captureLogs(): CapturingLogger
    {
        $logger = new CapturingLogger();
        static::getContainer()->set('logger', $logger);

        return $logger;
    }
}

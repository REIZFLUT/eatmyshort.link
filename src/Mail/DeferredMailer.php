<?php

declare(strict_types=1);

namespace App\Mail;

use Psr\Log\LoggerInterface;

/**
 * Runs work that does not belong into the request itself - mail delivery in
 * particular - after the response has been sent (see DeferredMailListener).
 * Failures never reach the user: they are logged and the response stays as is.
 */
final class DeferredMailer
{
    /** @var list<\Closure(): void> */
    private array $tasks = [];

    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function defer(\Closure $task): void
    {
        $this->tasks[] = $task;
    }

    public function flush(): void
    {
        $tasks = $this->tasks;
        $this->tasks = [];

        foreach ($tasks as $task) {
            try {
                $task();
            } catch (\Throwable $e) {
                $this->logger->error('Deferred mail failed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
            }
        }
    }
}

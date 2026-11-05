<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Mail\DeferredMailer;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sends the mails queued by DeferredMailer after the response went out, so a
 * slow or broken mail transport can neither time the request nor reveal
 * whether an account exists.
 */
#[AsEventListener(event: KernelEvents::TERMINATE)]
final readonly class DeferredMailListener
{
    public function __construct(
        private DeferredMailer $mailer,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(TerminateEvent $event): void
    {
        try {
            $this->mailer->flush();
        } catch (\Throwable $e) {
            $this->logger->error('Deferred mail queue could not be flushed: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
        }
    }
}

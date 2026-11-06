<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Entity\Link;
use App\Event\Link\LinkPersistingEvent;
use App\Link\LinkRejectedException;
use Doctrine\ORM\Event\PreFlushEventArgs;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Test-environment shim for the persistence hook that LinkManager owns.
 *
 * LinkManager (src/Link, outside this change) is the real dispatcher: it fires
 * LinkPersistingEvent before a write and throws LinkRejectedException when a plugin rejects.
 * This listener reproduces exactly that contract at the Doctrine flush boundary, so the API
 * controller's rejection handling can be exercised on its own. It is inert unless a
 * LinkPersistingEvent listener actually rejects.
 */
final readonly class LinkPersistingShim
{
    public function __construct(private EventDispatcherInterface $dispatcher)
    {
    }

    public function __invoke(PreFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();
        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof Link) {
                $this->guard($entity, true);
            }
        }
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof Link) {
                $this->guard($entity, false);
            }
        }
    }

    private function guard(Link $link, bool $isNew): void
    {
        $event = new LinkPersistingEvent($link, $isNew);
        $this->dispatcher->dispatch($event);
        if ($event->isRejected()) {
            throw new LinkRejectedException((string) $event->getRejectionMessage());
        }
    }
}

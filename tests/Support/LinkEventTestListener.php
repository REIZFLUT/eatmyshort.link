<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Event\Link\LinkDeletedEvent;
use App\Event\Link\LinkPersistingEvent;
use App\Event\Link\LinkSavedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Test double for a link-lifecycle plugin. Inert until a test sets the matching
 * EventTestState.
 */
final class LinkEventTestListener
{
    #[AsEventListener(event: LinkPersistingEvent::class)]
    public function onPersisting(LinkPersistingEvent $event): void
    {
        if (null !== EventTestState::$linkRejectionMessage) {
            $event->reject(EventTestState::$linkRejectionMessage);
        }
        if (EventTestState::$recordLinkLifecycle) {
            EventTestState::$persistedLinks[] = ['link' => $event->getLink(), 'isNew' => $event->isNew()];
        }
    }

    #[AsEventListener(event: LinkSavedEvent::class)]
    public function onSaved(LinkSavedEvent $event): void
    {
        if (EventTestState::$recordLinkLifecycle) {
            EventTestState::$savedLinks[] = ['link' => $event->getLink(), 'created' => $event->wasCreated()];
        }
    }

    #[AsEventListener(event: LinkDeletedEvent::class)]
    public function onDeleted(LinkDeletedEvent $event): void
    {
        if (EventTestState::$recordLinkLifecycle) {
            EventTestState::$deletedLinks[] = $event->getLink();
        }
    }
}

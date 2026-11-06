<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Event\Redirect\RedirectResolvingEvent;
use App\Event\Redirect\RedirectUnresolvedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Test double for a redirect plugin. Inert until a test sets the matching EventTestState.
 */
final class RedirectEventTestListener
{
    #[AsEventListener(event: RedirectResolvingEvent::class)]
    public function onResolving(RedirectResolvingEvent $event): void
    {
        if (null !== EventTestState::$redirectTarget) {
            $event->setTarget(EventTestState::$redirectTarget);
        }
        if (null !== EventTestState::$redirectStatus) {
            $event->setStatusCode(EventTestState::$redirectStatus);
        }
        if (null !== EventTestState::$redirectVetoReason) {
            $event->veto(EventTestState::$redirectVetoReason);
        }
    }

    #[AsEventListener(event: RedirectUnresolvedEvent::class)]
    public function onUnresolved(RedirectUnresolvedEvent $event): void
    {
        if (null !== EventTestState::$unresolvedTarget) {
            $event->setTarget(EventTestState::$unresolvedTarget, EventTestState::$unresolvedStatus);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Event\Link\ShortCodeGeneratingEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Test double for a short-code plugin. Inert until a test sets EventTestState::$shortCode.
 */
final class ShortCodeEventTestListener
{
    #[AsEventListener(event: ShortCodeGeneratingEvent::class)]
    public function onGenerating(ShortCodeGeneratingEvent $event): void
    {
        if (null !== EventTestState::$shortCode) {
            $event->setShortCode(EventTestState::$shortCode);
        }
    }
}

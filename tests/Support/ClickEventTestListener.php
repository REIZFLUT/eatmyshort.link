<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Event\Tracking\ClickRecordedEvent;
use App\Event\Tracking\ClickRecordingEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Test double for a tracking plugin. Inert until a test sets the matching EventTestState.
 */
final class ClickEventTestListener
{
    #[AsEventListener(event: ClickRecordingEvent::class)]
    public function onRecording(ClickRecordingEvent $event): void
    {
        if (null !== EventTestState::$clickVetoReason) {
            $event->veto(EventTestState::$clickVetoReason);
        }
    }

    #[AsEventListener(event: ClickRecordedEvent::class)]
    public function onRecorded(ClickRecordedEvent $event): void
    {
        if (EventTestState::$recordClicks) {
            EventTestState::$recordedClicks[] = $event;
        }
    }
}

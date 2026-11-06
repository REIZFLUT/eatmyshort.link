<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Event\Link\LinkPersistingEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Test stand-in for a plugin that refuses to persist a link.
 *
 * Silent until a test arms it with a message, then it rejects every write it sees - exactly
 * the branch LinkManager turns into a LinkRejectedException.
 */
final class LinkRejectionPluginListener
{
    private static ?string $message = null;

    public static function rejectWith(string $message): void
    {
        self::$message = $message;
    }

    public static function reset(): void
    {
        self::$message = null;
    }

    #[AsEventListener(event: LinkPersistingEvent::class)]
    public function __invoke(LinkPersistingEvent $event): void
    {
        if (null !== self::$message) {
            $event->reject(self::$message);
        }
    }
}

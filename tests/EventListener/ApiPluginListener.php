<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Event\Api\ApiResponseEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Test stand-in for a plugin that enriches API responses.
 *
 * Inert until a test arms it, so the rest of the suite keeps seeing the plain payloads.
 * The recorded operations let a test assert which endpoints dispatch the event.
 */
final class ApiPluginListener
{
    public const string MARKER = 'plugin_marker';

    private static bool $armed = false;

    /** @var list<string> */
    private static array $operations = [];

    public static function arm(): void
    {
        self::$armed = true;
    }

    public static function reset(): void
    {
        self::$armed = false;
        self::$operations = [];
    }

    /** @return list<string> */
    public static function operations(): array
    {
        return self::$operations;
    }

    #[AsEventListener(event: ApiResponseEvent::class)]
    public function __invoke(ApiResponseEvent $event): void
    {
        self::$operations[] = $event->getOperation();
        if (self::$armed) {
            $event->setField(self::MARKER, 'yes');
        }
    }
}

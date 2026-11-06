<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Event\Redirect\RedirectResolvingEvent;
use Plugin\UtmTagging\UtmTagger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Test stand-in for the utm-tagging plugin.
 *
 * The test environment points APP_PLUGIN_DIR at tests/Fixtures/plugins, so the real plugin is
 * not loaded through the whitelist. Its behaviour in the real redirect flow is still exercised
 * here by driving the real UtmTagger over controlled settings: the listener is inert until a
 * test arms it with withSettings(), so every other test sees the unmodified application.
 */
final class UtmTaggingPluginListener
{
    /** @var array<string, mixed> */
    private static array $settings = [];

    /** @param array<string, mixed> $settings */
    public static function withSettings(array $settings): void
    {
        self::$settings = $settings;
    }

    public static function reset(): void
    {
        self::$settings = [];
    }

    #[AsEventListener(event: RedirectResolvingEvent::class)]
    public function onRedirect(RedirectResolvingEvent $event): void
    {
        if ([] === self::$settings) {
            return;
        }

        $event->setTarget((new UtmTagger(self::$settings))->tag($event->getTarget()));
    }
}

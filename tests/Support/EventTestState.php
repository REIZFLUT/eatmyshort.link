<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Link;
use App\Event\Tracking\ClickRecordedEvent;

/**
 * Mutable state that drives the event test listeners and collects what they saw.
 *
 * The listeners are registered in the test container only (#[AsEventListener] +
 * autoconfigure under when@test), but "registered" is not "active": every hook defaults
 * to inert and only acts once a test sets a value here, so no other test is affected.
 * EventTestCase resets this state in setUp() and tearDown() because static properties
 * live for the whole PHPUnit process.
 */
final class EventTestState
{
    /** Non-null: rewrite the redirect target. */
    public static ?string $redirectTarget = null;

    /** Non-null: replace the redirect status code. */
    public static ?int $redirectStatus = null;

    /** Non-null: veto the redirect with this reason. */
    public static ?string $redirectVetoReason = null;

    /** Non-null: turn an unresolved path into a redirect to this target. */
    public static ?string $unresolvedTarget = null;

    public static int $unresolvedStatus = 302;

    /** Non-null: veto the click with this reason. */
    public static ?string $clickVetoReason = null;

    /** Non-null: reject every link write with this message. */
    public static ?string $linkRejectionMessage = null;

    /** Non-null: suggest this code for the next generated short code. */
    public static ?string $shortCode = null;

    public static bool $recordLinkLifecycle = false;

    public static bool $recordClicks = false;

    /** @var list<ClickRecordedEvent> */
    public static array $recordedClicks = [];

    /** @var list<array{link: Link, created: bool}> */
    public static array $savedLinks = [];

    /** @var list<array{link: Link, isNew: bool}> */
    public static array $persistedLinks = [];

    /** @var list<Link> */
    public static array $deletedLinks = [];

    /** @return list<ClickRecordedEvent> */
    public static function recordedClicks(): array
    {
        return self::$recordedClicks;
    }

    /** @return list<array{link: Link, created: bool}> */
    public static function savedLinks(): array
    {
        return self::$savedLinks;
    }

    /** @return list<array{link: Link, isNew: bool}> */
    public static function persistedLinks(): array
    {
        return self::$persistedLinks;
    }

    /** @return list<Link> */
    public static function deletedLinks(): array
    {
        return self::$deletedLinks;
    }

    public static function reset(): void
    {
        self::$redirectTarget = null;
        self::$redirectStatus = null;
        self::$redirectVetoReason = null;
        self::$unresolvedTarget = null;
        self::$unresolvedStatus = 302;
        self::$clickVetoReason = null;
        self::$linkRejectionMessage = null;
        self::$shortCode = null;
        self::$recordLinkLifecycle = false;
        self::$recordClicks = false;
        self::$recordedClicks = [];
        self::$savedLinks = [];
        self::$persistedLinks = [];
        self::$deletedLinks = [];
    }
}

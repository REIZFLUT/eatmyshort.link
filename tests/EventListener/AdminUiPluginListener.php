<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Event\Admin\AdminNavBuildingEvent;
use App\Event\Admin\DashboardWidgetsEvent;
use App\Plugin\Ui\AdminNavItem;
use App\Plugin\Ui\AdminWidget;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Test stand-in for a plugin that contributes dashboard widgets and admin-nav entries.
 *
 * Nothing is contributed until a test configures it, so other tests render the app unchanged.
 */
final class AdminUiPluginListener
{
    /** @var list<AdminWidget> */
    private static array $widgets = [];

    /** @var list<AdminNavItem> */
    private static array $navItems = [];

    public static function withWidgets(AdminWidget ...$widgets): void
    {
        self::$widgets = array_values($widgets);
    }

    public static function withNavItems(AdminNavItem ...$items): void
    {
        self::$navItems = array_values($items);
    }

    public static function reset(): void
    {
        self::$widgets = [];
        self::$navItems = [];
        self::$lastDashboard = null;
    }

    /** The last dashboard event seen, so tests can check the viewer context it carried. */
    public static ?DashboardWidgetsEvent $lastDashboard = null;

    #[AsEventListener(event: DashboardWidgetsEvent::class)]
    public function onDashboard(DashboardWidgetsEvent $event): void
    {
        self::$lastDashboard = $event;
        foreach (self::$widgets as $widget) {
            $event->add($widget);
        }
    }

    #[AsEventListener(event: AdminNavBuildingEvent::class)]
    public function onAdminNav(AdminNavBuildingEvent $event): void
    {
        foreach (self::$navItems as $item) {
            $event->add($item);
        }
    }
}

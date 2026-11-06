<?php

declare(strict_types=1);

namespace App\Twig;

use App\Event\Admin\AdminNavBuildingEvent;
use App\Plugin\Ui\AdminNavItem;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * Twig entry point for plugin admin UI that is part of the layout.
 *
 * The admin navigation lives in base.html.twig, which every controller renders - there is no
 * single controller that could collect the plugin entries. They are therefore gathered lazily,
 * only when the template actually asks for them, and only inside the admin-only branch of the
 * navigation. Plugin pages ship their own route and access control (AdminNavBuildingEvent).
 */
final readonly class PluginExtension
{
    public function __construct(private EventDispatcherInterface $dispatcher)
    {
    }

    /** @return list<AdminNavItem> */
    #[AsTwigFunction('plugin_admin_nav')]
    public function adminNav(): array
    {
        $event = new AdminNavBuildingEvent();
        $this->dispatcher->dispatch($event);

        return $event->all();
    }
}

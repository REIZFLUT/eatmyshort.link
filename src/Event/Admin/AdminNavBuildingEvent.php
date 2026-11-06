<?php

declare(strict_types=1);

namespace App\Event\Admin;

use App\Plugin\Ui\AdminNavItem;

/**
 * The admin navigation is being rendered; plugins may contribute entries.
 *
 * Entries are appended after the built-in ones. A plugin that adds a page ships its own
 * route (plugins/<name>/config/routes.yaml) and is responsible for access control on it.
 *
 * @api
 *
 * @since 1.0
 */
final class AdminNavBuildingEvent
{
    /** @var list<AdminNavItem> */
    private array $items = [];

    public function add(AdminNavItem $item): void
    {
        $this->items[] = $item;
    }

    /** @return list<AdminNavItem> */
    public function all(): array
    {
        return $this->items;
    }
}

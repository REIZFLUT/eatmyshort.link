<?php

declare(strict_types=1);

namespace App\Event\Admin;

use App\Plugin\Ui\AdminWidget;

/**
 * The dashboard is being rendered; plugins may contribute their own cards.
 *
 * Widgets append after the built-in ones and are rendered in the order they were added.
 * Values are plain strings - a plugin formats its own numbers, the app does not know
 * what they mean.
 *
 * @api
 *
 * @since 1.0
 */
final class DashboardWidgetsEvent
{
    /** @var list<AdminWidget> */
    private array $widgets = [];

    public function add(AdminWidget $widget): void
    {
        $this->widgets[] = $widget;
    }

    public function has(string $id): bool
    {
        foreach ($this->widgets as $widget) {
            if ($widget->id === $id) {
                return true;
            }
        }

        return false;
    }

    /** @return list<AdminWidget> */
    public function all(): array
    {
        return $this->widgets;
    }
}

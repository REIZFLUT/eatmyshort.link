<?php

declare(strict_types=1);

namespace App\Event\Admin;

use App\Entity\User;
use App\Plugin\Ui\AdminWidget;

/**
 * The dashboard is being rendered; plugins may contribute their own cards.
 *
 * The dashboard is the start page of EVERY signed-in user, not an admin page. A card that
 * shows installation-wide figures (all clicks, all users) must therefore check
 * {@see self::isViewerAdmin()} first, and a per-user card should scope its numbers to
 * {@see self::getViewer()} - otherwise every user sees data that is not theirs.
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

    /**
     * @param bool $viewerIsAdmin the effective admin right (role AND IP allowlist, see
     *                            AdminAccess), not just the stored role
     */
    public function __construct(
        private readonly User $viewer,
        private readonly bool $viewerIsAdmin,
    ) {
    }

    /** The signed-in user the dashboard is rendered for. */
    public function getViewer(): User
    {
        return $this->viewer;
    }

    /** Whether the viewer may see installation-wide data right now. */
    public function isViewerAdmin(): bool
    {
        return $this->viewerIsAdmin;
    }

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

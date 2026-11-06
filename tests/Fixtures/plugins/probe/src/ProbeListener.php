<?php

declare(strict_types=1);

namespace Plugin\Probe;

use App\Event\Admin\DashboardWidgetsEvent;
use App\Plugin\Ui\AdminWidget;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Listens to an existing core event, which proves a plugin is wired into the shared event
 * dispatcher through autoconfiguration, not through the app knowing about the plugin.
 */
#[AsEventListener]
final readonly class ProbeListener
{
    public function __construct(private ProbeRecorder $recorder)
    {
    }

    public function __invoke(DashboardWidgetsEvent $event): void
    {
        $event->add(new AdminWidget('probe', 'Probe', 'loaded'));
        $this->recorder->record();
    }
}

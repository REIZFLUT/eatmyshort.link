<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Maintenance window for bin/deploy.sh: while the flag file exists, every request gets
 * a 503 with Retry-After. That keeps old OPcache code from serving requests against a
 * half-migrated database while the deploy swaps the container and reloads PHP-FPM.
 *
 * The check is a single stat() call per request and runs before the router.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 2048)]
final readonly class MaintenanceListener
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $flag = $this->projectDir.'/var/maintenance';
        if (!is_file($flag)) {
            return;
        }

        $event->setResponse(new Response(
            "Wartung\n\nDie Anwendung wird gerade aktualisiert. Bitte in einer Minute neu laden.\n",
            Response::HTTP_SERVICE_UNAVAILABLE,
            [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Retry-After' => '60',
                'Cache-Control' => 'no-store, private',
            ],
        ));
    }
}

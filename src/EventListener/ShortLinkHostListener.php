<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Controller\RedirectController;
use App\Security\ShortLinkHostMatcher;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sends every request on a short-link host straight to the RedirectController,
 * before the router runs (priority 32). The app routes therefore only exist on
 * the admin host(s), and no short code can ever shadow an app URL.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 64)]
final readonly class ShortLinkHostListener
{
    public function __construct(private ShortLinkHostMatcher $matcher)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$this->matcher->matches($request)) {
            return;
        }

        $request->attributes->set('_controller', RedirectController::class);
        $request->attributes->set('_route', 'short_link');
        $request->attributes->set('_stateless', true);
    }
}

<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Security\AdminIpPolicy;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

/**
 * Blocks /admin for addresses outside the admin allowlist. Runs before the
 * firewall (priority 8), so a blocked address never even sees the login form
 * for admin pages. Normal user pages stay reachable.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 9)]
final readonly class AdminIpGuardListener
{
    public function __construct(
        private AdminIpPolicy $policy,
        private Environment $twig,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || 'short_link' === $request->attributes->get('_route')) {
            return;
        }
        $path = $request->getPathInfo();
        if ('/admin' !== $path && !str_starts_with($path, '/admin/')) {
            return;
        }

        $ip = $request->getClientIp();
        if ($this->policy->allows($ip)) {
            return;
        }

        $this->logger->warning('Admin area blocked for {ip}', ['ip' => $ip, 'path' => $path]);
        $event->setResponse(new Response(
            $this->twig->render('security/ip_blocked.html.twig', ['ip' => $ip]),
            Response::HTTP_FORBIDDEN,
            ['Cache-Control' => 'no-store, private'],
        ));
    }
}

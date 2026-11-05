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
 *
 * The primary decision uses the already resolved route name (RouterListener has
 * priority 32 and runs before this listener): encoded paths such as /%61dmin
 * are decoded by the router, so only the route name is reliable. The path check
 * stays as a second line of defence for requests the router did not match.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 9)]
final readonly class AdminIpGuardListener
{
    private const int MAX_DECODE_PASSES = 5;

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
        if (!$this->isAdminRequest($request->attributes->get('_route'), $request->getPathInfo())) {
            return;
        }

        $ip = $request->getClientIp();
        if ($this->policy->allows($ip)) {
            return;
        }

        $this->logger->warning('Admin area blocked for {ip}', ['ip' => $ip, 'path' => $request->getPathInfo(), 'route' => $request->attributes->get('_route')]);
        $event->setResponse(new Response(
            $this->twig->render('security/ip_blocked.html.twig', ['ip' => $ip]),
            Response::HTTP_FORBIDDEN,
            ['Cache-Control' => 'no-store, private'],
        ));
    }

    private function isAdminRequest(mixed $route, string $path): bool
    {
        if (\is_string($route) && str_starts_with($route, 'app_admin')) {
            return true;
        }

        // Fallback for requests without a route (404) and for encoded paths the
        // router could not match; decode repeatedly to catch double encoding.
        $path = $this->decodePath($path);

        return '/admin' === $path || str_starts_with($path, '/admin/');
    }

    private function decodePath(string $path): string
    {
        for ($i = 0; $i < self::MAX_DECODE_PASSES; ++$i) {
            $decoded = rawurldecode($path);
            if ($decoded === $path) {
                break;
            }
            $path = $decoded;
        }

        return $path;
    }
}

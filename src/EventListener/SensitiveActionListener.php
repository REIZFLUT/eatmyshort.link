<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Security\SensitiveConfirmation;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Actions that disable a second factor, remove a passkey or create a permanent
 * API token must not be reachable with a hijacked session alone: they need a
 * fresh re-authentication (ProfileController::confirm()) within five minutes.
 *
 * Priority 5 runs after the firewall (8), so the security token is available.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
final readonly class SensitiveActionListener
{
    /** @var array<string, string> route name => confirmation target */
    private const array PROTECTED_ROUTES = [
        'app_profile_totp_disable' => 'security',
        'app_profile_totp_backup_codes' => 'security',
        'app_profile_passkey_delete' => 'security',
        'webauthn.controller.creation.request.profile_passkey' => 'security',
        'webauthn.controller.creation.response.profile_passkey' => 'security',
    ];

    public function __construct(
        private Security $security,
        private SensitiveConfirmation $confirmation,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');
        $target = self::PROTECTED_ROUTES[$route] ?? ('app_profile_tokens' === $route && $request->isMethod('POST') ? 'tokens' : null);
        if (null === $target || !$this->security->getUser() instanceof User) {
            return;
        }
        if ($this->confirmation->isConfirmed($request->hasSession() ? $request->getSession() : null)) {
            return;
        }

        $event->setResponse($this->wantsJson($route, $request)
            ? new JsonResponse(['error' => 'confirmation_required'], Response::HTTP_FORBIDDEN)
            : new RedirectResponse($this->urls->generate('app_profile_confirm', ['target' => $target])));
    }

    /** The WebAuthn endpoints are called by JavaScript and always expect JSON. */
    private function wantsJson(string $route, Request $request): bool
    {
        return str_starts_with($route, 'webauthn.')
            || str_contains((string) $request->headers->get('Accept'), 'application/json');
    }
}

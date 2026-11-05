<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Repository\WebauthnCredentialRepository;
use App\Settings\Settings;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * With "2FA required for admins" switched on, an admin without TOTP and without
 * a passkey can only reach the security page until one of them is set up.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
final readonly class TwoFactorRequirementListener
{
    private const array ALLOWED_ROUTE_PREFIXES = ['app_profile_security', 'app_profile_totp', 'app_profile_passkey', 'app_logout', 'webauthn', '_wdt', '_profiler'];

    public function __construct(
        private Security $security,
        private Settings $settings,
        private WebauthnCredentialRepository $credentials,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');
        if (!$event->isMainRequest() || 'short_link' === $route || str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }
        foreach (self::ALLOWED_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return;
            }
        }

        if ($this->security->getToken() instanceof TwoFactorTokenInterface) {
            return;
        }
        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->isAdmin() || !$this->settings->getBool(Settings::REQUIRE_2FA_ADMINS)) {
            return;
        }
        if ($user->isTotpAuthenticationEnabled() || $this->credentials->countByUser($user) > 0) {
            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('warning', 'flash.2fa_required');
        }
        $event->setResponse(new RedirectResponse($this->urls->generate('app_profile_security')));
    }
}

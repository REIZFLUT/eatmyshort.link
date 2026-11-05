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
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Enforces "2FA required for admins".
 *
 * A registered passkey only counts as a second factor when the session was actually
 * opened with it. A password-only session of an admin who owns a passkey is dropped -
 * on every route, including the profile pages: otherwise it could simply enrol its own
 * TOTP and upgrade itself past the requirement.
 *
 * An admin with no second factor at all may only reach the pages that set one up.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
final readonly class TwoFactorRequirementListener
{
    /** Routes that must stay reachable in every state: the login flow itself and logout. */
    private const array AUTH_ROUTE_PREFIXES = ['app_login', 'app_logout', 'webauthn', '_wdt', '_profiler'];

    /** Pages that enrol a second factor; the only ones open to an admin without any. */
    private const array SETUP_ROUTE_PREFIXES = ['app_profile_security', 'app_profile_totp', 'app_profile_passkey'];

    public function __construct(
        private Security $security,
        private Settings $settings,
        private WebauthnCredentialRepository $credentials,
        private UrlGeneratorInterface $urls,
        private TokenStorageInterface $tokens,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route');
        if (!$event->isMainRequest() || 'short_link' === $route || str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }
        if (self::matches($route, self::AUTH_ROUTE_PREFIXES)) {
            return;
        }
        if ($this->security->getToken() instanceof TwoFactorTokenInterface) {
            return;
        }
        $user = $this->security->getUser();
        if (!$user instanceof User || !$user->isAdmin() || !$this->settings->getBool(Settings::REQUIRE_2FA_ADMINS)) {
            return;
        }
        if ($user->isTotpAuthenticationEnabled()) {
            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        $method = $session instanceof SessionInterface ? $session->get(LoginSuccessListener::SESSION_AUTH_METHOD) : null;

        if ($this->credentials->countByUser($user) > 0) {
            // Passkey registered: only a passkey login counts. Sessions from before the
            // marker existed are treated like password logins (fail closed).
            if (LoginSuccessListener::METHOD_PASSKEY !== $method) {
                $this->refuse($event, $session, 'flash.password_login_passkey_only');
            }

            return;
        }

        if (self::matches($route, self::SETUP_ROUTE_PREFIXES)) {
            return;
        }

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('warning', 'flash.2fa_required');
        }
        $event->setResponse(new RedirectResponse($this->urls->generate('app_profile_security')));
    }

    /** @param list<string> $prefixes */
    private static function matches(string $route, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($route, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function refuse(RequestEvent $event, ?SessionInterface $session, string $flash): void
    {
        $this->tokens->setToken(null);
        $session?->invalidate();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('danger', $flash);
        }
        $event->setResponse(new RedirectResponse($this->urls->generate('app_login')));
    }
}

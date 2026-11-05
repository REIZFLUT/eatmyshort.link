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
 * With "2FA required for admins" switched on, an admin without TOTP and without
 * a passkey can only reach the security page until one of them is set up.
 *
 * A registered passkey only counts as a second factor when the session was
 * actually opened with it: an admin who signs in with the password alone is
 * logged out again instead of being let through.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
final readonly class TwoFactorRequirementListener
{
    // app_login: the login POST itself has to finish, the guard kicks in on the next request.
    private const array ALLOWED_ROUTE_PREFIXES = ['app_login', 'app_profile_security', 'app_profile_totp', 'app_profile_passkey', 'app_logout', 'webauthn', '_wdt', '_profiler'];

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
        if ($user->isTotpAuthenticationEnabled()) {
            return;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        if ($this->credentials->countByUser($user) > 0) {
            // A passkey is registered, so the account has a second factor - but
            // only a passkey login uses it. Refuse the password-only session.
            if ($session instanceof SessionInterface && LoginSuccessListener::METHOD_PASSWORD === $session->get(LoginSuccessListener::SESSION_AUTH_METHOD)) {
                $this->tokens->setToken(null);
                $session->invalidate();
                if ($session instanceof FlashBagAwareSessionInterface) {
                    $session->getFlashBag()->add('danger', 'flash.password_login_passkey_only');
                }
                $event->setResponse(new RedirectResponse($this->urls->generate('app_login')));
            }

            return;
        }

        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('warning', 'flash.2fa_required');
        }
        $event->setResponse(new RedirectResponse($this->urls->generate('app_profile_security')));
    }
}

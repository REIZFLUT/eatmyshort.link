<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Webauthn\Bundle\Security\Http\Authenticator\WebauthnAuthenticator;

#[AsEventListener]
final readonly class LoginSuccessListener
{
    /** Session key with the factor that authenticated this session. */
    public const string SESSION_AUTH_METHOD = '_emsl_auth_method';
    public const string METHOD_PASSWORD = 'password';
    public const string METHOD_PASSKEY = 'passkey';

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User || 'main' !== $event->getFirewallName()) {
            return;
        }
        $user->setLastLoginAt(new \DateTimeImmutable());
        $this->em->flush();

        $request = $event->getRequest();
        if ($request->hasSession()) {
            // Remember how the user signed in: a password login must not count as
            // a second factor just because a passkey is registered.
            $request->getSession()->set(
                self::SESSION_AUTH_METHOD,
                $event->getAuthenticator() instanceof WebauthnAuthenticator ? self::METHOD_PASSKEY : self::METHOD_PASSWORD,
            );
        }

        if (null !== $user->getLocale() && $request->hasSession()) {
            $request->getSession()->set('_locale', $user->getLocale());
        }
    }
}

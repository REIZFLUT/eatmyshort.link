<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\WebauthnCredential;
use App\EventListener\LoginSuccessListener;
use App\Security\SensitiveConfirmation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

/**
 * Sensitive profile actions need a fresh confirmation. A passkey sign-in counts as one,
 * a password sign-in does not; passkey-only accounts re-authenticate on /login?reauth=1.
 */
final class ReauthenticationTest extends AppTestCase
{
    public function testLoginPageRedirectsAwayWhenAlreadySignedIn(): void
    {
        $this->client->loginUser($this->createUser('ida@example.com'));

        $this->client->request('GET', '/login');
        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/');
    }

    public function testReauthLoginPageStaysReachableForSignedInUsers(): void
    {
        $this->client->loginUser($this->createUser('jane@example.com'));

        $this->client->request('GET', '/login?reauth=1');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('button[data-success-url$="/profile/security"]', 'the passkey button returns to the profile');
        self::assertStringContainsString('bereits angemeldet', (string) $this->client->getResponse()->getContent());
    }

    public function testPasskeyLoginOpensTheConfirmationWindow(): void
    {
        $session = $this->dispatchLogin('webauthn.controller.security.main.request.result', $this->createUser('kim@example.com'));

        self::assertSame(LoginSuccessListener::METHOD_PASSKEY, $session->get(LoginSuccessListener::SESSION_AUTH_METHOD));
        $confirmedAt = $session->get(SensitiveConfirmation::SESSION_KEY);
        self::assertIsInt($confirmedAt);
        self::assertLessThanOrEqual(5, time() - $confirmedAt);
    }

    public function testPasswordLoginDoesNotOpenTheConfirmationWindow(): void
    {
        $session = $this->dispatchLogin('app_login', $this->createUser('loup@example.com'));

        self::assertSame(LoginSuccessListener::METHOD_PASSWORD, $session->get(LoginSuccessListener::SESSION_AUTH_METHOD));
        self::assertNull($session->get(SensitiveConfirmation::SESSION_KEY), 'a password login is no confirmation');
    }

    public function testPasskeyOnlyAccountIsOfferedThePasskeyRoute(): void
    {
        $user = $this->createUser('mona@example.com', withPassword: false);
        $this->addPasskey($user);
        $this->client->loginUser($user);

        $this->client->request('GET', '/profile/confirm');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="/login?reauth=1"]', 'passkey-only accounts must be able to confirm');

        // Without a passkey there is no way to confirm, so the page points at the password form.
        $this->client->request('GET', '/profile/confirm', server: ['REMOTE_ADDR' => '203.0.113.11']);
        self::assertResponseIsSuccessful();
    }

    /** Runs the listener the way the firewall does after a successful login. */
    private function dispatchLogin(string $route, User $user): Session
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->attributes->set('_route', $route);
        $request->setSession($session);

        $token = $this->createStub(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        $passport = $this->createStub(Passport::class);
        $passport->method('getUser')->willReturn($user);

        $event = new LoginSuccessEvent(
            $this->createStub(AuthenticatorInterface::class),
            $passport,
            $token,
            $request,
            null,
            'main',
        );
        static::getContainer()->get(LoginSuccessListener::class)($event);

        return $session;
    }

    private function addPasskey(User $user): void
    {
        $record = new CredentialRecord(
            publicKeyCredentialId: base64_encode(random_bytes(16)),
            type: 'public-key',
            transports: ['internal'],
            attestationType: 'none',
            trustPath: EmptyTrustPath::create(),
            aaguid: Uuid::v4(),
            credentialPublicKey: random_bytes(77),
            userHandle: $user->getWebauthnHandle(),
            counter: 0,
        );
        $this->em()->persist(WebauthnCredential::fromRecord($record, $user, 'Test-Passkey'));
        $this->em()->flush();
    }
}

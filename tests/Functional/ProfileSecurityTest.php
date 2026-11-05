<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ApiToken;
use App\Entity\User;
use App\Security\SecretBox;
use OTPHP\TOTP;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * S4: disabling TOTP, creating API tokens and registering passkeys need a fresh
 * re-authentication within five minutes.
 */
final class ProfileSecurityTest extends AppTestCase
{
    public function testTotpDisableWithoutConfirmationRedirectsToConfirm(): void
    {
        $user = $this->createTotpUser();
        $this->client->loginUser($user);

        $this->client->request('GET', '/profile/security');
        self::assertSelectorExists('form[action$="/profile/confirm"]', 'the confirm form replaces the actions');
        self::assertSelectorNotExists('form[action$="/profile/totp/disable"]', 'no disable button without confirmation');

        // Token obtained while confirmed, then the confirmation is dropped again.
        $this->confirmInSession();
        $token = $this->tokenFrom('/profile/security', '/profile/totp/disable');
        $this->session()->remove('_sensitive_confirmed_at');
        $this->session()->save();

        $this->client->request('POST', '/profile/totp/disable', ['_token' => $token]);

        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/profile/confirm?target=security');
        self::assertTrue($this->fresh($user)->isTotpAuthenticationEnabled(), 'TOTP stays enabled');
    }

    public function testConfirmWithPasswordAllowsTotpDisable(): void
    {
        $user = $this->createTotpUser();
        $this->client->loginUser($user);

        $this->confirmWithPassword('security');

        $this->client->request('POST', '/profile/totp/disable', ['_token' => $this->tokenFrom('/profile/security', '/profile/totp/disable')]);
        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/profile/security');
        self::assertFalse($this->fresh($user)->isTotpEnabled());
    }

    public function testConfirmWithWrongPasswordDoesNotOpenTheWindow(): void
    {
        $user = $this->createTotpUser();
        $this->client->loginUser($user);

        $this->client->request('POST', '/profile/confirm', [
            'password' => 'falsch',
            'target' => 'security',
            '_token' => $this->tokenFrom('/profile/confirm?target=security', '/profile/confirm'),
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.errors', 'fehlgeschlagen');
        self::assertNull($this->session()->get('_sensitive_confirmed_at'));
        self::assertTrue($this->fresh($user)->isTotpAuthenticationEnabled());
    }

    public function testConfirmPageAsksForThePassword(): void
    {
        $this->client->loginUser($this->createUser('ada@example.com'));

        $this->client->request('GET', '/profile/confirm?target=tokens');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[type=password][name=password]');
        self::assertSelectorExists('input[name=target][value=tokens]');
    }

    public function testUnknownTargetFallsBackToTheSecurityPage(): void
    {
        $this->client->loginUser($this->createUser('ada@example.com'));

        $this->client->request('POST', '/profile/confirm', [
            'password' => self::PASSWORD,
            'target' => 'https://evil.example.com',
            '_token' => $this->tokenFrom('/profile/confirm', '/profile/confirm'),
        ]);

        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/profile/security', message: 'no open redirect');
    }

    public function testApiTokenNeedsConfirmation(): void
    {
        $user = $this->createUser('ada@example.com');
        $this->client->loginUser($user);

        $this->client->request('GET', '/profile/tokens');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('input[name=name]', message: 'the create form stays hidden');
        self::assertSelectorExists('input[type=password][name=password]');

        $this->confirmInSession();
        $token = $this->tokenFrom('/profile/tokens', '/profile/tokens');
        $this->session()->remove('_sensitive_confirmed_at');
        $this->session()->save();

        $this->client->request('POST', '/profile/tokens', ['name' => 'CRM', 'days' => '0', '_token' => $token]);

        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/profile/confirm?target=tokens');
        self::assertSame(0, $this->em()->getRepository(ApiToken::class)->count([]));
    }

    public function testApiTokenWorksAfterConfirmation(): void
    {
        $this->client->loginUser($this->createUser('ada@example.com'));

        $this->confirmWithPassword('tokens');

        $this->client->request('POST', '/profile/tokens', [
            'name' => 'CRM',
            'days' => '0',
            '_token' => $this->tokenFrom('/profile/tokens', '/profile/tokens'),
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->em()->getRepository(ApiToken::class)->count([]));
    }

    public function testConfirmExpiresAfterFiveMinutes(): void
    {
        $user = $this->createTotpUser();
        $this->client->loginUser($user);

        $this->confirmWithPassword('security');
        $token = $this->tokenFrom('/profile/security', '/profile/totp/disable');

        $this->session()->set('_sensitive_confirmed_at', time() - 301);
        $this->session()->save();

        $this->client->request('POST', '/profile/totp/disable', ['_token' => $token]);

        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/profile/confirm?target=security');
        self::assertTrue($this->fresh($user)->isTotpAuthenticationEnabled());
    }

    public function testPasswordlessUserConfirmsWithTotpCode(): void
    {
        $user = $this->createTotpUser(withPassword: false);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/profile/confirm?target=security');
        self::assertSelectorExists('input[name=code]');
        self::assertSelectorNotExists('input[name=password]');
        $token = $crawler->filter('form[action$="/profile/confirm"] input[name=_token]')->attr('value');
        self::assertNotNull($token);

        $this->client->request('POST', '/profile/confirm', [
            'code' => $this->currentTotpCode(),
            'target' => 'security',
            '_token' => $token,
        ]);

        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/profile/security');
    }

    public function testAccountWithoutPasswordAndTotpCannotConfirm(): void
    {
        $this->client->loginUser($this->createUser('passkey@example.com', withPassword: false));

        $this->client->request('GET', '/profile/confirm');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[action$="/profile/confirm"]', 'no form for accounts without a credential');
        self::assertSelectorTextContains('.card', 'Lege zuerst ein Passwort fest');
    }

    public function testPasskeyRegistrationRequestIsForbiddenWithoutConfirmation(): void
    {
        $this->client->loginUser($this->createUser('ada@example.com'));

        $this->client->request('POST', '/profile/passkeys/options', server: [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ORIGIN' => 'https://'.self::ADMIN_HOST,
        ]);

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('content-type', 'application/json');
        self::assertStringContainsString('confirmation_required', (string) $this->client->getResponse()->getContent());
    }

    private const string TOTP_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    private function createTotpUser(bool $withPassword = true): User
    {
        $user = $this->createUser('ada@example.com', withPassword: $withPassword);
        $user->setTotpSecret(self::TOTP_SECRET, static::getContainer()->get(SecretBox::class)->encrypt(self::TOTP_SECRET))
            ->setTotpEnabled(true)
            ->replaceBackupCodes(['abcd-efgh-jkmn']);
        $this->em()->flush();

        return $user;
    }

    private function currentTotpCode(): string
    {
        return TOTP::createFromSecret(self::TOTP_SECRET)->now();
    }

    private function confirmWithPassword(string $target): void
    {
        $this->client->request('POST', '/profile/confirm', [
            'password' => self::PASSWORD,
            'target' => $target,
            '_token' => $this->tokenFrom('/profile/confirm?target='.$target, '/profile/confirm'),
        ]);
        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/profile/'.('tokens' === $target ? 'tokens' : 'security'));
    }

    private function confirmInSession(): void
    {
        $this->session()->set('_sensitive_confirmed_at', time());
        $this->session()->save();
    }

    private function session(): SessionInterface
    {
        return $this->client->getRequest()->getSession();
    }

    private function tokenFrom(string $path, string $action): string
    {
        $crawler = $this->client->request('GET', $path);
        $token = $crawler->filter(sprintf('form[action$="%s"] input[name=_token]', $action))->attr('value');
        self::assertNotNull($token, 'CSRF token for '.$action);

        return $token;
    }

    public function testConfirmationRefusesWhenTheLimitIsExhausted(): void
    {
        // The limiter storage is reset between requests in the test environment, so the
        // exhausted state is simulated: the controller has to consult it and answer 429.
        // It must be replaced before the first request, the container is already built then.
        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn(new RateLimit(0, new \DateTimeImmutable('+15 minutes'), false, 5));
        $factory = $this->createStub(RateLimiterFactoryInterface::class);
        $factory->method('create')->willReturn($limiter);
        static::getContainer()->set('limiter.profile_confirm', $factory);

        $this->client->loginUser($this->createUser('throttle@example.com'));
        $crawler = $this->client->request('GET', '/profile/confirm');
        // Scope to the confirmation form: the layout also renders a logout form with a _token.
        $token = $crawler->filter('form[action="/profile/confirm"] input[name=_token]')->attr('value');

        $this->client->request('POST', '/profile/confirm', [
            'password' => self::PASSWORD,
            'target' => 'security',
            '_token' => $token,
        ]);

        self::assertResponseStatusCodeSame(429);
        self::assertSelectorTextContains('.errors', 'Zu viele');

        // The window stays closed: the security page still asks for a confirmation instead
        // of offering the action itself.
        $this->client->request('GET', '/profile/security');
        self::assertSelectorExists('form[action="/profile/confirm"]');
        self::assertSelectorNotExists('form[action="/profile/totp/disable"]');
    }

    public function testConfirmationLimiterAllowsFiveAttemptsPerQuarterHour(): void
    {
        /** @var RateLimiterFactoryInterface $factory */
        $factory = static::getContainer()->get('limiter.profile_confirm');
        $key = 'user-'.bin2hex(random_bytes(4));

        for ($attempt = 1; $attempt <= 5; ++$attempt) {
            self::assertTrue($factory->create($key)->consume()->isAccepted(), 'attempt '.$attempt.' is allowed');
        }
        self::assertFalse($factory->create($key)->consume()->isAccepted(), 'the sixth attempt is refused');
    }
}

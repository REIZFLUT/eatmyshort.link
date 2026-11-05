<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Security\SecretBox;
use OTPHP\TOTP;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final class SecurityTest extends AppTestCase
{
    public function testAnonymousIsSentToLogin(): void
    {
        $this->client->request('GET', '/');
        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/login');

        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-passkey="login"]');
    }

    public function testPasswordLogin(): void
    {
        $this->createUser('ada@example.com');

        $this->login('ada@example.com', self::PASSWORD);
        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Ada');
    }

    public function testWrongPasswordAndInactiveUser(): void
    {
        $user = $this->createUser('ada@example.com');

        $this->login('ada@example.com', 'wrong');
        $this->client->followRedirect();
        self::assertSelectorExists('.errors');

        $this->fresh($user)->setActive(false);
        $this->em()->flush();
        $this->login('ada@example.com', self::PASSWORD);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.errors', 'gesperrt');
    }

    public function testLockingOrDemotingEndsRunningSessions(): void
    {
        $user = $this->createUser('ada@example.com', admin: true);
        $this->login('ada@example.com', self::PASSWORD);
        $this->client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();

        $this->fresh($user)->setAdmin(false);
        $this->em()->flush();
        $this->client->request('GET', '/links');
        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/login', message: 'role change logs out');

        $this->login('ada@example.com', self::PASSWORD);
        $this->client->request('GET', '/links');
        self::assertResponseIsSuccessful();
        $this->fresh($user)->setActive(false);
        $this->em()->flush();
        $this->client->request('GET', '/links');
        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/login', message: 'locked users lose their session');
    }

    public function testTotpIsRequiredAfterPasswordLogin(): void
    {
        $user = $this->createUser('ada@example.com');
        $secret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
        $user->setTotpSecret($secret, static::getContainer()->get(SecretBox::class)->encrypt($secret))->setTotpEnabled(true)->replaceBackupCodes(['aaaa-bbbb']);
        $this->em()->flush();

        $this->login('ada@example.com', self::PASSWORD);
        $this->client->request('GET', '/links');
        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/2fa', message: 'nothing is reachable before the second factor');

        $this->client->request('GET', '/2fa');
        $this->client->submitForm('Bestätigen', ['_auth_code' => '000000']);
        $this->client->followRedirect();
        self::assertSelectorExists('.errors');
        self::assertSelectorTextContains('.errors', 'Bestätigungs-Code ist nicht korrekt', 'the 2FA error is translated');

        $code = TOTP::createFromSecret($secret)->now();
        $this->client->submitForm('Bestätigen', ['_auth_code' => $code]);
        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/links', message: 'back to the page that was asked for');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testBackupCodeWorksOnce(): void
    {
        $user = $this->createUser('ada@example.com');
        $secret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
        $user->setTotpSecret($secret, static::getContainer()->get(SecretBox::class)->encrypt($secret))->setTotpEnabled(true)->replaceBackupCodes(['aaaa-bbbb']);
        $this->em()->flush();

        $this->login('ada@example.com', self::PASSWORD);
        $this->client->request('GET', '/2fa');
        $this->client->submitForm('Bestätigen', ['_auth_code' => 'aaaa-bbbb']);
        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/');

        $this->em()->clear();
        $fresh = $this->em()->find($user::class, $user->getId());
        self::assertSame(0, $fresh?->countBackupCodes());
    }

    public function testTotpSecretIsEncryptedAtRest(): void
    {
        $user = $this->createUser('ada@example.com');
        $box = static::getContainer()->get(SecretBox::class);
        $user->setTotpSecret('JBSWY3DPEHPK3PXP', $box->encrypt('JBSWY3DPEHPK3PXP'))->setTotpEnabled(true);
        $this->em()->flush();

        $stored = $this->em()->getConnection()->fetchOne('SELECT totp_secret_encrypted FROM app_user WHERE id = ?', [$user->getId()]);
        self::assertIsString($stored);
        self::assertStringNotContainsString('JBSWY3DPEHPK3PXP', $stored);

        $this->em()->clear();
        self::assertSame('JBSWY3DPEHPK3PXP', $this->em()->find($user::class, $user->getId())?->getTotpSecret());
    }

    public function testInviteLinkSetsPassword(): void
    {
        $user = $this->createUser('neu@example.com', withPassword: false);
        $result = static::getContainer()->get(\App\Mail\AccountMailer::class)->sendAccountLink($user, 'invite');
        self::assertStringStartsWith('https://'.self::ADMIN_HOST.'/invite/', $result['url']);

        $path = (string) parse_url($result['url'], PHP_URL_PATH);
        $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Passwort speichern', [
            'change_password[plainPassword][first]' => 'ein-langes-sicheres-passwort',
            'change_password[plainPassword][second]' => 'ein-langes-sicheres-passwort',
        ], serverParameters: ['HTTP_ORIGIN' => 'http://'.self::ADMIN_HOST]);
        self::assertResponseRedirects('/login');

        $this->client->request('GET', $path);
        self::assertResponseStatusCodeSame(410, 'links work only once');

        $this->login('neu@example.com', 'ein-langes-sicheres-passwort');
        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/');
    }

    public function testResetMailsAreLimitedPerAddress(): void
    {
        // Unique address per run: limiter state lives in a cache pool.
        $email = 'limit-'.bin2hex(random_bytes(4)).'@example.com';
        $user = $this->createUser($email);
        $token = $this->resetCsrfToken('203.0.113.10');

        // Four requests for one mailbox from four different addresses.
        foreach (['203.0.113.10', '203.0.113.11', '203.0.113.12', '203.0.113.13'] as $ip) {
            $this->client->request('POST', '/reset', ['email' => $email, '_token' => $token], server: ['REMOTE_ADDR' => $ip]);
            self::assertResponseRedirects('/login', message: 'the answer never changes');
        }

        self::assertLessThanOrEqual(3, (int) $this->em()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM reset_token WHERE user_id = ?',
            [$user->getId()],
        ), 'at most three reset mails per address and hour');
    }

    public function testPerMailboxLimiterAllowsThreeMailsPerHour(): void
    {
        // The limit cannot be observed over HTTP here: in the test environment the
        // rate limiter pool is an ArrayAdapter that is reset between requests.
        $limiter = static::getContainer()->get('limiter.password_reset_email');
        self::assertInstanceOf(RateLimiterFactoryInterface::class, $limiter);

        $key = 'limiter-'.bin2hex(random_bytes(4)).'@example.com';
        foreach ([1, 2, 3] as $attempt) {
            self::assertTrue($limiter->create($key)->consume()->isAccepted(), 'mail '.$attempt.' is allowed');
        }
        self::assertFalse($limiter->create($key)->consume()->isAccepted(), 'the fourth mail within an hour is refused');
    }

    public function testResetRequestDoesNotRevealUnknownAddresses(): void
    {
        $known = 'known-'.bin2hex(random_bytes(4)).'@example.com';
        $this->createUser($known);
        $unknown = 'ghost-'.bin2hex(random_bytes(4)).'@example.com';
        $token = $this->resetCsrfToken('203.0.113.20');

        $this->client->request('POST', '/reset', ['email' => $known, '_token' => $token], server: ['REMOTE_ADDR' => '203.0.113.20']);
        $knownResponse = $this->client->getResponse();

        $rowsBefore = (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM reset_token');
        $this->client->request('POST', '/reset', ['email' => $unknown, '_token' => $token], server: ['REMOTE_ADDR' => '203.0.113.21']);
        $unknownResponse = $this->client->getResponse();

        self::assertSame($knownResponse->getStatusCode(), $unknownResponse->getStatusCode());
        self::assertSame($knownResponse->headers->get('Location'), $unknownResponse->headers->get('Location'));
        self::assertSame($rowsBefore, (int) $this->em()->getConnection()->fetchOne('SELECT COUNT(*) FROM reset_token'), 'no token for an unknown address');
    }

    public function testLogoutNeedsPostAndCsrfToken(): void
    {
        $this->client->loginUser($this->createUser('ada@example.com'));

        $this->client->request('GET', '/logout');
        self::assertResponseStatusCodeSame(405, 'logout is no longer a GET endpoint');
        $this->client->request('GET', '/links');
        self::assertResponseIsSuccessful('a GET on /logout does not log out');

        $this->client->request('POST', '/logout');
        $this->client->request('GET', '/links');
        self::assertResponseIsSuccessful('a POST without a token does not log out');

        $crawler = $this->client->request('GET', '/links');
        $token = $crawler->filter('form[action="/logout"] input[name=_token]')->attr('value');
        $this->client->request('POST', '/logout', ['_token' => $token], server: ['HTTP_ORIGIN' => 'http://'.self::ADMIN_HOST]);
        self::assertResponseRedirects('/login');

        $this->client->request('GET', '/links');
        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/login', message: 'the session is gone');
    }

    private function resetCsrfToken(string $ip): string
    {
        $crawler = $this->client->request('GET', '/reset', server: ['REMOTE_ADDR' => $ip]);
        self::assertResponseIsSuccessful();

        return (string) $crawler->filter('form input[name=_token]')->attr('value');
    }

    private function login(string $email, string $password): void
    {
        $this->client->request('POST', '/login', [
            'email' => $email,
            'password' => $password,
            '_csrf_token' => 'csrf-token',
        ], server: ['HTTP_ORIGIN' => 'http://'.self::ADMIN_HOST]);
    }
}

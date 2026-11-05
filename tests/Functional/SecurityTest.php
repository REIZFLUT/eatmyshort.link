<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Security\SecretBox;
use OTPHP\TOTP;

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

    private function login(string $email, string $password): void
    {
        $this->client->request('POST', '/login', [
            'email' => $email,
            'password' => $password,
            '_csrf_token' => 'csrf-token',
        ], server: ['HTTP_ORIGIN' => 'http://'.self::ADMIN_HOST]);
    }
}

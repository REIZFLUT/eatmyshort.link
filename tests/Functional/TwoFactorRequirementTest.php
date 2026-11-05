<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use App\Entity\WebauthnCredential;
use App\Security\SecretBox;
use App\Settings\Settings;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

/**
 * "2FA required for admins": a registered passkey only counts when the session
 * was opened with it. A password login must not slip past the requirement.
 */
final class TwoFactorRequirementTest extends AppTestCase
{
    public function testPasswordLoginIsRefusedWhenOnlyAPasskeyExists(): void
    {
        $user = $this->createUser('ada@example.com', admin: true);
        $this->addPasskey($user);
        static::getContainer()->get(Settings::class)->save([Settings::REQUIRE_2FA_ADMINS => true]);

        $this->login('ada@example.com', self::PASSWORD);
        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/');

        $this->client->request('GET', '/admin/users');
        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/login', message: 'a password-only session is not a second factor');

        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash-danger', 'Passkey');

        $this->client->request('GET', '/links');
        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/login', message: 'the admin is logged out');
    }

    public function testAdminWithoutAnySecondFactorIsSentToTheSecurityPage(): void
    {
        $user = $this->createUser('bob@example.com', admin: true);
        static::getContainer()->get(Settings::class)->save([Settings::REQUIRE_2FA_ADMINS => true]);
        $this->client->loginUser($user);

        $this->client->request('GET', '/admin/users');
        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/profile/security');

        $this->client->request('GET', '/links');
        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/profile/security', message: 'nothing but the security page is reachable');
    }

    public function testAdminWithTotpReachesTheAdminArea(): void
    {
        $user = $this->createUser('carl@example.com', admin: true);
        $secret = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
        $user->setTotpSecret($secret, static::getContainer()->get(SecretBox::class)->encrypt($secret))->setTotpEnabled(true);
        $this->em()->flush();
        static::getContainer()->get(Settings::class)->save([Settings::REQUIRE_2FA_ADMINS => true]);
        $this->client->loginUser($user);

        $this->client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();
    }

    public function testRequirementIsOffByDefault(): void
    {
        $user = $this->createUser('dora@example.com', admin: true);
        $this->addPasskey($user);

        $this->login('dora@example.com', self::PASSWORD);
        $this->client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();
    }

    public function testPasswordSessionCannotEnrolItsOwnTotpWhenAPasskeyExists(): void
    {
        $user = $this->createUser('eve@example.com', admin: true);
        $this->addPasskey($user);
        static::getContainer()->get(Settings::class)->save([Settings::REQUIRE_2FA_ADMINS => true]);

        $this->login('eve@example.com', self::PASSWORD);

        // Setting up a factor must not upgrade a password-only session: the setup pages are
        // reachable in the allowlist of the listener, so the passkey check has to come first.
        $this->client->request('GET', '/profile/totp');
        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/login', message: 'no self-service upgrade past the requirement');

        $this->client->request('GET', '/admin/settings');
        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/login');
    }

    public function testAdminWithoutSecondFactorCanStillSetUpTotp(): void
    {
        $user = $this->createUser('frank@example.com', admin: true);
        static::getContainer()->get(Settings::class)->save([Settings::REQUIRE_2FA_ADMINS => true]);

        $this->login('frank@example.com', self::PASSWORD);
        $this->client->request('GET', '/profile/totp');
        self::assertResponseIsSuccessful('an admin without any factor has to be able to enrol one');
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

    private function login(string $email, string $password): void
    {
        $this->client->request('POST', '/login', [
            'email' => $email,
            'password' => $password,
            '_csrf_token' => 'csrf-token',
        ], server: ['HTTP_ORIGIN' => 'http://'.self::ADMIN_HOST]);
    }
}

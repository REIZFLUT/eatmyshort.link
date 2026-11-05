<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AdminIpRule;

final class AdminIpGuardTest extends AppTestCase
{
    public function testAdminAreaIsOpenWithoutRules(): void
    {
        $this->client->loginUser($this->createUser('root@example.com', admin: true));

        $this->client->request('GET', '/admin/users');
        self::assertResponseIsSuccessful();
    }

    public function testRulesBlockOtherAddressesButNotUserPages(): void
    {
        $admin = $this->createUser('root@example.com', admin: true);
        $this->em()->persist(new AdminIpRule('10.0.0.0/8', 'VPN'));
        $this->em()->flush();
        $this->client->loginUser($admin);

        $this->client->request('GET', '/admin/users');
        self::assertResponseStatusCodeSame(403);
        self::assertSelectorTextContains('h2', 'Zugriff gesperrt');

        $this->client->request('GET', '/links');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/admin/users', server: ['REMOTE_ADDR' => '10.1.2.3']);
        self::assertResponseIsSuccessful();
    }

    public function testBlockedEvenBeforeLogin(): void
    {
        $this->em()->persist(new AdminIpRule('10.0.0.0/8'));
        $this->em()->flush();

        $this->client->request('GET', '/admin/settings');
        self::assertResponseStatusCodeSame(403, 'no login form for blocked addresses');
    }

    public function testCannotLockYourselfOut(): void
    {
        $this->client->loginUser($this->createUser('root@example.com', admin: true));

        $crawler = $this->client->request('GET', '/admin/ip-rules');
        $token = $crawler->filter('input[name=_token]')->attr('value');

        $this->client->request('POST', '/admin/ip-rules', ['cidr' => '10.0.0.0/8', 'label' => 'VPN', '_token' => $token]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em()->getRepository(AdminIpRule::class)->count([]));

        $this->client->request('POST', '/admin/ip-rules', ['cidr' => '203.0.113.0/24', 'label' => 'Büro', '_token' => $token]);
        self::assertResponseRedirects('/admin/ip-rules');
        self::assertSame(1, $this->em()->getRepository(AdminIpRule::class)->count([]));
    }

    public function testNonAdminsAreDenied(): void
    {
        $this->client->loginUser($this->createUser('user@example.com'));

        $this->client->request('GET', '/admin/users');
        self::assertResponseStatusCodeSame(403);
    }
}

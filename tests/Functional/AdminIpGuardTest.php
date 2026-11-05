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

    public function testEncodedAdminPathsAreBlocked(): void
    {
        $admin = $this->createUser('root@example.com', admin: true);
        $this->em()->persist(new AdminIpRule('10.0.0.0/8', 'VPN'));
        $this->em()->flush();
        $this->client->loginUser($admin);

        // The router decodes the path before matching, so /%61dmin used to slip past the guard.
        foreach (['/admin/users', '/%61dmin/users', '/%61dmin/settings'] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseStatusCodeSame(403, 'blocked from outside: '.$path);
        }

        // Double encoding matches no route at all, so the router 404s before anything runs.
        $this->client->request('GET', '/%2561dmin/users');
        self::assertResponseStatusCodeSame(404, 'a doubly encoded path never reaches admin code');

        foreach (['/admin/users', '/%61dmin/users', '/%61dmin/settings'] as $path) {
            $this->client->request('GET', $path, server: ['REMOTE_ADDR' => '10.1.2.3']);
            self::assertResponseIsSuccessful('allowed from the VPN: '.$path);
        }
    }

    public function testAdminOutsideAllowlistActsLikeNormalUser(): void
    {
        $admin = $this->createUser('root@example.com', admin: true);
        $bob = $this->createUser('bob@example.com');
        $domain = $this->createDomain('go.example.com', $bob);
        $bobsLink = $this->createLink($bob, $domain, 'bob');
        $this->createLink($admin, $domain, 'root');
        $this->em()->persist(new AdminIpRule('10.0.0.0/8'));
        $this->em()->flush();
        $this->client->loginUser($admin);

        $this->client->request('GET', '/links');
        self::assertSelectorTextContains('table', 'go.example.com/root');
        self::assertSelectorTextNotContains('table', 'go.example.com/bob');
        self::assertSelectorNotExists('a[href="/admin/users"]', 'no admin navigation from outside');
        $this->client->request('GET', '/links/'.$bobsLink->getId());
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/links', server: ['REMOTE_ADDR' => '10.9.8.7']);
        self::assertSelectorTextContains('table', 'go.example.com/bob');
        $this->client->request('GET', '/links/'.$bobsLink->getId(), server: ['REMOTE_ADDR' => '10.9.8.7']);
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
        // Several forms on the page carry a _token (logout, delete); take the add form's.
        $token = $crawler->filter('form.form-row input[name=_token]')->attr('value');

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

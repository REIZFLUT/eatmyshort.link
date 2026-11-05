<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Click;
use App\Entity\Link;

final class LinkManagementTest extends AppTestCase
{
    public function testUserCreatesLinkWithRandomCodeOnAssignedDomain(): void
    {
        $user = $this->createUser('user@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $this->client->loginUser($user);

        $this->client->request('GET', '/links/new');
        $this->client->submitForm('Link anlegen', [
            'link[targetUrl]' => 'https://www.example.com/a',
            'link[domain]' => (string) $domain->getId(),
            'link[code]' => '',
        ], serverParameters: ['HTTP_ORIGIN' => 'http://'.self::ADMIN_HOST]);
        self::assertResponseRedirects();

        $link = $this->em()->getRepository(Link::class)->findOneBy(['targetUrl' => 'https://www.example.com/a']);
        self::assertNotNull($link);
        self::assertMatchesRegularExpression('/^[23456789abcdefghjkmnpqrstuvwxyz]{6}$/', $link->getCode());
        self::assertTrue($link->isActive());
        self::assertSame(302, $link->getRedirectType());
    }

    public function testValidationRejectsTakenReservedAndLoopingLinks(): void
    {
        $user = $this->createUser('user@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $this->createLink($user, $domain, 'sommer');
        $this->client->loginUser($user);

        foreach ([
            ['sommer', 'https://www.example.com/', 'schon vergeben'],
            ['admin', 'https://www.example.com/', 'reserviert'],
            ['neu', 'https://go.example.com/sommer', 'eigene Kurzlink'],
            ['neu2', 'javascript:alert(1)', 'http(s)'],
        ] as [$code, $target, $message]) {
            $this->client->request('GET', '/links/new');
            $this->client->submitForm('Link anlegen', [
                'link[targetUrl]' => $target,
                'link[domain]' => (string) $domain->getId(),
                'link[code]' => $code,
            ], serverParameters: ['HTTP_ORIGIN' => 'http://'.self::ADMIN_HOST]);
            self::assertResponseStatusCodeSame(422, $code);
            self::assertSelectorTextContains('.errors', $message);
        }
    }

    public function testDomainAssignmentIsEnforced(): void
    {
        $user = $this->createUser('user@example.com');
        $this->createDomain('go.example.com', $user);
        $foreign = $this->createDomain('vorstand.example.com');
        $this->client->loginUser($user);

        $this->client->request('GET', '/links/new');
        self::assertSelectorNotExists('option[value="'.$foreign->getId().'"]');
        // Inject the foreign domain id by hand.
        $this->client->request('POST', '/links/new', ['link' => [
            'targetUrl' => 'https://www.example.com/',
            'domain' => (string) $foreign->getId(),
            'code' => 'heimlich',
            'redirectType' => '302',
            'active' => '1',
            '_token' => 'csrf-token',
        ]], server: ['HTTP_ORIGIN' => 'http://'.self::ADMIN_HOST]);
        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->em()->getRepository(Link::class)->findOneBy(['code' => 'heimlich']));
    }

    public function testUsersOnlySeeTheirOwnLinks(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob = $this->createUser('bob@example.com');
        $admin = $this->createUser('root@example.com', admin: true);
        $domain = $this->createDomain('go.example.com', $alice, $bob);
        $bobsLink = $this->createLink($bob, $domain, 'bob');
        $this->createLink($alice, $domain, 'alice');

        $this->client->loginUser($alice);
        $this->client->request('GET', '/links');
        self::assertSelectorTextContains('table', 'go.example.com/alice');
        self::assertSelectorTextNotContains('table', 'go.example.com/bob');

        foreach (['', '/edit', '/qr.svg', '/export.csv'] as $suffix) {
            $this->client->request('GET', '/links/'.$bobsLink->getId().$suffix);
            self::assertResponseStatusCodeSame(403, $suffix);
        }

        $this->client->loginUser($admin);
        $this->client->request('GET', '/links/'.$bobsLink->getId());
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/links');
        self::assertSelectorTextContains('table', 'go.example.com/bob');
    }

    public function testEditAddsLocalisationAndDeleteRemovesLink(): void
    {
        $user = $this->createUser('user@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $link = $this->createLink($user, $domain, 'produkt', 'https://www.example.com/de');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/links/'.$link->getId().'/edit');
        $form = $crawler->selectButton('Speichern')->form();
        $values = $form->getPhpValues();
        $values['link']['localisations'] = [['language' => 'EN', 'targetUrl' => 'https://www.example.com/en']];
        $this->client->request('POST', $form->getUri(), $values, server: ['HTTP_ORIGIN' => 'http://'.self::ADMIN_HOST]);
        self::assertResponseRedirects('/links/'.$link->getId());

        $this->shortRequest('go.example.com', '/produkt', ['Accept-Language' => 'en']);
        self::assertResponseRedirects('https://www.example.com/en');

        $crawler = $this->client->request('GET', '/links/'.$link->getId().'/edit');
        $this->client->submit($crawler->filter('form[data-confirm]')->form());
        self::assertResponseRedirects('/links');
        $this->em()->clear();
        self::assertNull($this->em()->find(Link::class, $link->getId()));
    }

    public function testDuplicateLocalisationLanguagesAreRejected(): void
    {
        $user = $this->createUser('user@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/links/new');
        $form = $crawler->selectButton('Link anlegen')->form();
        $values = $form->getPhpValues();
        $values['link']['targetUrl'] = 'https://www.example.com/';
        $values['link']['domain'] = (string) $domain->getId();
        $values['link']['code'] = 'doppelt';
        $values['link']['localisations'] = [
            ['language' => 'en', 'targetUrl' => 'https://www.example.com/en'],
            ['language' => 'EN', 'targetUrl' => 'https://www.example.com/en-2'],
        ];
        $this->client->request('POST', $form->getUri(), $values, server: ['HTTP_ORIGIN' => 'http://'.self::ADMIN_HOST]);

        self::assertResponseStatusCodeSame(422, 'a duplicate language must not become a database 500');
        self::assertSelectorTextContains('.errors', 'Jede Sprache');
        self::assertNull($this->em()->getRepository(Link::class)->findOneBy(['code' => 'doppelt']));
    }

    public function testExportStreamsManyClicks(): void
    {
        $user = $this->createUser('user@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $link = $this->createLink($user, $domain, 'viele');

        $at = new \DateTimeImmutable('-1 hour');
        for ($i = 0; $i < 250; ++$i) {
            $this->em()->persist(new Click($link, $at->modify(\sprintf('+%d seconds', $i)), 'export-hash-'.$i));
        }
        $this->em()->flush();
        $this->em()->clear();

        $this->client->loginUser($user);
        $this->client->request('GET', '/links/'.$link->getId().'/export.csv?days=365');
        self::assertResponseIsSuccessful();

        ob_start();
        $this->client->getResponse()->sendContent();
        $csv = (string) ob_get_clean();
        if ('' === $csv) {
            // The test client already consumed the streamed response; use its buffer.
            $csv = (string) $this->client->getInternalResponse()->getContent();
        }

        self::assertSame(251, substr_count(trim($csv), "\n") + 1, 'header plus 250 rows');
    }
}

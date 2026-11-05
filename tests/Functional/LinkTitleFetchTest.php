<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Domain;
use App\Entity\Link;
use App\Settings\Settings;
use App\Tests\HttpClient\MockResponseFactory;

final class LinkTitleFetchTest extends AppTestCase
{
    public function testFormCreateStoresTheTitleFetchedThroughTheMockedClient(): void
    {
        $user = $this->createUser('titel@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $this->client->loginUser($user);

        $this->submitNewLink($domain, 'mittitel', 'https://93.184.216.34/titel-test');

        $link = $this->em()->getRepository(Link::class)->findOneBy(['code' => 'mittitel']);
        self::assertNotNull($link);
        self::assertSame(MockResponseFactory::TITLE, $link->getTitle());
    }

    public function testDisabledSettingLeavesTheTitleEmpty(): void
    {
        $user = $this->createUser('titel@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        static::getContainer()->get(Settings::class)->save([Settings::LINK_FETCH_TITLE => false]);
        $this->client->loginUser($user);

        $this->submitNewLink($domain, 'ohnetitel', 'https://93.184.216.34/ohne-titel');

        $link = $this->em()->getRepository(Link::class)->findOneBy(['code' => 'ohnetitel']);
        self::assertNotNull($link);
        self::assertNull($link->getTitle());
    }

    public function testManuallyEnteredTitleIsNotOverwritten(): void
    {
        $user = $this->createUser('titel@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $this->client->loginUser($user);

        $this->submitNewLink($domain, 'eigener', 'https://93.184.216.34/eigener', 'Eigener Titel');

        $link = $this->em()->getRepository(Link::class)->findOneBy(['code' => 'eigener']);
        self::assertNotNull($link);
        self::assertSame('Eigener Titel', $link->getTitle());
    }

    private function submitNewLink(Domain $domain, string $code, string $target, ?string $title = null): void
    {
        $this->client->request('GET', '/links/new');
        $values = [
            'link[targetUrl]' => $target,
            'link[domain]' => (string) $domain->getId(),
            'link[code]' => $code,
        ];
        if (null !== $title) {
            $values['link[title]'] = $title;
        }
        $this->client->submitForm('Link anlegen', $values, serverParameters: ['HTTP_ORIGIN' => 'http://'.self::ADMIN_HOST]);
        self::assertResponseRedirects();
    }
}

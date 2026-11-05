<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Click;
use App\Entity\LinkLocalisation;

final class RedirectTest extends AppTestCase
{
    private const string CHROME = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';

    public function testRedirectsPerHostAndRecordsClick(): void
    {
        $owner = $this->createUser('owner@example.com');
        $go = $this->createDomain('go.example.com');
        $other = $this->createDomain('links.example.org');
        $link = $this->createLink($owner, $go, 'sommer', 'https://www.example.com/sommer');
        $this->createLink($owner, $other, 'sommer', 'https://www.example.org/anders');

        $this->shortRequest('go.example.com', '/Sommer', ['User-Agent' => self::CHROME, 'Referer' => 'https://news.example.net/x', 'Accept-Language' => 'de-DE,de;q=0.9']);

        self::assertResponseRedirects('https://www.example.com/sommer', 302);
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertResponseNotHasCookie('emsl_session');

        $this->shortRequest('links.example.org', '/sommer');
        self::assertResponseRedirects('https://www.example.org/anders', 302);

        $link = $this->fresh($link);
        self::assertSame(1, $link->getClickCount());
        $click = $this->em()->getRepository(Click::class)->findOneBy(['link' => $link]);
        self::assertNotNull($click);
        self::assertSame('news.example.net', $click->getReferrerHost());
        self::assertSame('de', $click->getLanguage());
        self::assertSame('Chrome', $click->getBrowser());
        self::assertSame('203.0.113.0', $click->getIpPrefix(), 'only the /24 network is stored');
        self::assertFalse($click->isBot());
    }

    public function testBotsHeadAndPrefetchAreNotCounted(): void
    {
        $owner = $this->createUser('owner@example.com');
        $link = $this->createLink($owner, $this->createDomain('go.example.com'), 'x1');

        $this->shortRequest('go.example.com', '/x1', ['User-Agent' => 'Googlebot/2.1 (+http://www.google.com/bot.html)']);
        self::assertResponseRedirects();
        $this->shortRequest('go.example.com', '/x1', ['User-Agent' => self::CHROME], 'HEAD');
        $this->shortRequest('go.example.com', '/x1', ['User-Agent' => self::CHROME, 'Sec-Purpose' => 'prefetch']);

        $link = $this->fresh($link);
        self::assertSame(0, $link->getClickCount());
        self::assertCount(1, $this->em()->getRepository(Click::class)->findBy(['link' => $link]), 'bot click is stored, but flagged');
    }

    public function testLocalisedTargets(): void
    {
        $owner = $this->createUser('owner@example.com');
        $link = $this->createLink($owner, $this->createDomain('go.example.com'), 'produkt', 'https://www.example.com/de/produkt');
        $link->addLocalisation(new LinkLocalisation()->setLanguage('en')->setTargetUrl('https://www.example.com/en/product'));
        $this->em()->flush();

        $this->shortRequest('go.example.com', '/produkt', ['Accept-Language' => 'en-GB,en;q=0.8']);
        self::assertResponseRedirects('https://www.example.com/en/product');

        $this->shortRequest('go.example.com', '/produkt', ['Accept-Language' => 'fr-FR']);
        self::assertResponseRedirects('https://www.example.com/de/produkt');
    }

    public function testUnknownInactiveAndExpired(): void
    {
        $owner = $this->createUser('owner@example.com');
        $domain = $this->createDomain('go.example.com');
        $this->createLink($owner, $domain, 'aus')->setActive(false);
        $this->createLink($owner, $domain, 'alt')->setExpiresAt(new \DateTimeImmutable('-1 minute'));
        $this->em()->flush();

        $this->shortRequest('unknown.example.com', '/aus');
        self::assertResponseStatusCodeSame(404);

        $this->shortRequest('go.example.com', '/gibtsnicht');
        self::assertResponseStatusCodeSame(404);

        $this->shortRequest('go.example.com', '/aus');
        self::assertResponseStatusCodeSame(410);

        $this->shortRequest('go.example.com', '/alt');
        self::assertResponseStatusCodeSame(410);

        $this->fresh($domain)->setActive(false);
        $this->em()->flush();
        $this->shortRequest('go.example.com', '/aus');
        self::assertResponseStatusCodeSame(404);
    }

    public function testDomainFallbacks(): void
    {
        $domain = $this->createDomain('go.example.com')
            ->setRootRedirectUrl('https://www.example.com/')
            ->setNotFoundUrl('https://www.example.com/')
            ->setForwardUnknownPaths(true);
        $this->em()->flush();

        $this->shortRequest('go.example.com', '/');
        self::assertResponseRedirects('https://www.example.com/');

        $this->shortRequest('go.example.com', '/karriere/jobs');
        self::assertResponseRedirects('https://www.example.com/karriere/jobs');

        $this->fresh($domain)->setForwardUnknownPaths(false);
        $this->em()->flush();
        $this->shortRequest('go.example.com', '/karriere');
        self::assertResponseRedirects('https://www.example.com/');
    }

    public function testAppRoutesDoNotExistOnShortDomains(): void
    {
        $this->createDomain('go.example.com');

        $this->shortRequest('go.example.com', '/login');
        self::assertResponseStatusCodeSame(404);
        $this->shortRequest('go.example.com', '/admin/users');
        self::assertResponseStatusCodeSame(404);
    }
}

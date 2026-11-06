<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Click;
use App\Tests\Support\EventTestState;

/**
 * The RedirectResolvingEvent / RedirectUnresolvedEvent contract, driven through real
 * HTTP requests: a plugin can change the target, pick another status, veto, or answer an
 * otherwise unknown path.
 */
final class RedirectEventTest extends EventTestCase
{
    private const string CHROME = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';

    public function testListenerCanRewriteTheRedirectTarget(): void
    {
        $owner = $this->createUser('owner@example.com');
        $this->createLink($owner, $this->createDomain('go.example.com'), 'kampagne', 'https://www.example.com/original');

        EventTestState::$redirectTarget = 'https://www.example.com/kampagne?utm_source=plugin';

        $this->shortRequest('go.example.com', '/kampagne', ['User-Agent' => self::CHROME]);

        self::assertResponseRedirects('https://www.example.com/kampagne?utm_source=plugin', 302);
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
    }

    public function testListenerCanChangeTheStatusCode(): void
    {
        $owner = $this->createUser('owner@example.com');
        $this->createLink($owner, $this->createDomain('go.example.com'), 'dauerhaft', 'https://www.example.com/');

        EventTestState::$redirectStatus = 301;

        $this->shortRequest('go.example.com', '/dauerhaft', ['User-Agent' => self::CHROME]);

        // The changed status drives the response and its cache headers.
        self::assertResponseRedirects('https://www.example.com/', 301);
        self::assertResponseHeaderSame('Vary', 'Accept-Language');
        self::assertStringContainsString('public', (string) $this->client->getResponse()->headers->get('Cache-Control'));
    }

    public function testVetoLooksLikeAnUnknownLinkAndIsNotCounted(): void
    {
        $owner = $this->createUser('owner@example.com');
        $link = $this->createLink($owner, $this->createDomain('go.example.com'), 'blockiert', 'https://www.example.com/');

        EventTestState::$redirectVetoReason = 'geo blocked';
        $logger = $this->captureLogs();

        $this->shortRequest('go.example.com', '/blockiert', ['User-Agent' => self::CHROME]);

        // Indistinguishable from an unknown link: 404 and the not-found page (which echoes
        // the requested path).
        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('.card', 'blockiert');
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex');

        $link = $this->fresh($link);
        self::assertSame(0, $link->getClickCount(), 'a vetoed redirect must not be counted');
        self::assertCount(0, $this->em()->getRepository(Click::class)->findBy(['link' => $link]));
        self::assertTrue($logger->hasRecord('info', 'veto'), 'the veto reason must reach the log');
    }

    public function testUnresolvedPathCanBeAnsweredByAPlugin(): void
    {
        $this->createDomain('go.example.com');

        EventTestState::$unresolvedTarget = 'https://www.example.com/landing';
        EventTestState::$unresolvedStatus = 302;

        $this->shortRequest('go.example.com', '/tippfehler');

        self::assertResponseRedirects('https://www.example.com/landing', 302);
    }

    public function testUnresolvedHookKeepsTheConfiguredFallbackWithoutATarget(): void
    {
        $this->createDomain('go.example.com')
            ->setNotFoundUrl('https://www.example.com/')
            ->setForwardUnknownPaths(true);
        $this->em()->flush();

        $this->shortRequest('go.example.com', '/karriere/jobs');

        self::assertResponseRedirects('https://www.example.com/karriere/jobs');
    }

    public function testUnresolvedHookSeesANullDomainForAnUnknownHost(): void
    {
        // No domain is registered for that host, so the event must be dispatched with null.
        EventTestState::$unresolvedTarget = 'https://www.example.com/catch-all';

        $this->shortRequest('voellig-unbekannt.example.net', '/xyz');

        self::assertResponseRedirects('https://www.example.com/catch-all', 302);
    }
}

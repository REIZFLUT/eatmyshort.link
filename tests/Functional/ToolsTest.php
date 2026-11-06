<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Link;

/**
 * The bookmarklet page, the query prefill of the link form and the popup (quick) flow.
 */
final class ToolsTest extends AppTestCase
{
    public function testBookmarkletPageIsLoginProtectedAndListsFourSnippets(): void
    {
        $this->client->request('GET', '/tools/bookmarklet');
        self::assertResponseRedirects('http://'.self::ADMIN_HOST.'/login');

        $user = $this->createUser('user@example.com');
        $this->createDomain('go.example.com', $user);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/tools/bookmarklet');
        self::assertResponseIsSuccessful();
        self::assertCount(4, $crawler->filter('a[href^="javascript:"]'), 'four bookmarklet variants');

        // Each snippet points at the host that serves this installation.
        $hrefs = [];
        for ($i = 0; $i < 4; ++$i) {
            $href = (string) $crawler->filter('a[href^="javascript:"]')->eq($i)->attr('href');
            self::assertStringContainsString(self::ADMIN_HOST, $href);
            self::assertStringContainsString('encodeURIComponent', $href);
            $hrefs[] = $href;
        }

        self::assertStringContainsString('/links/new?url=', $hrefs[0], 'standard opens the form');
        self::assertStringContainsString('/links/quick?url=', $hrefs[1], 'instant uses the quick flow');
        self::assertStringContainsString("prompt(decodeURIComponent('Wunschk%C3%BCrzel", $hrefs[2], 'custom asks for a code');
        self::assertStringContainsString("'&goto=1'", $hrefs[3], 'goto redirects to the target');

        self::assertStringContainsString(self::ADMIN_HOST, (string) $this->client->getResponse()->getContent());
    }

    public function testNewFormPrefillsTargetAndTitleFromQuery(): void
    {
        $user = $this->createUser('user@example.com');
        $this->createDomain('go.example.com', $user);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/links/new?url='.urlencode('https://www.example.com/x').'&title='.urlencode('Hallo Welt'));
        self::assertResponseIsSuccessful();
        self::assertSame('https://www.example.com/x', $crawler->filter('#link_targetUrl')->attr('value'));
        self::assertSame('Hallo Welt', $crawler->filter('#link_title')->attr('value'));
    }

    public function testNewFormIgnoresImplausibleQueryValues(): void
    {
        $user = $this->createUser('user@example.com');
        $this->createDomain('go.example.com', $user);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/links/new?url='.urlencode('javascript:alert(1)'));

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('javascript', (string) $crawler->filter('#link_targetUrl')->attr('value'));
        self::assertStringNotContainsString('javascript:alert', (string) $this->client->getResponse()->getContent());
    }

    public function testQuickFlowNeedsTokenAndCreatesOneLink(): void
    {
        $user = $this->createUser('user@example.com');
        $this->createDomain('go.example.com', $user);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/links/quick?url='.urlencode('https://www.example.com/x').'&title=Hallo');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/links/quick"] input[name="_token"]');
        self::assertSelectorTextContains('.card', 'https://www.example.com/x');
        $token = (string) $crawler->filter('form[action="/links/quick"] input[name="_token"]')->attr('value');
        self::assertNotSame('', $token);

        // POST without a token is refused and creates nothing.
        $this->client->request('POST', '/links/quick', ['url' => 'https://www.example.com/x']);
        self::assertResponseStatusCodeSame(403);
        self::assertCount(0, $this->em()->getRepository(Link::class)->findAll());

        $this->client->request('POST', '/links/quick', [
            '_token' => $token,
            'url' => 'https://www.example.com/x',
            'title' => 'Hallo',
            'code' => '',
        ]);
        self::assertResponseRedirects();
        self::assertMatchesRegularExpression('#/links/\d+$#', (string) $this->client->getResponse()->headers->get('Location'));

        $links = $this->em()->getRepository(Link::class)->findBy(['targetUrl' => 'https://www.example.com/x']);
        self::assertCount(1, $links, 'exactly one link is created');
        self::assertSame('Hallo', $links[0]->getTitle(), 'the title sent by the bookmarklet is kept, not fetched again');
        $shortUrl = $links[0]->getShortUrl();

        $this->client->followRedirect();
        self::assertStringContainsString($shortUrl, (string) $this->client->getResponse()->getContent());
    }

    public function testQuickGotoRedirectsToTarget(): void
    {
        $user = $this->createUser('user@example.com');
        $this->createDomain('go.example.com', $user);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/links/quick?url='.urlencode('https://www.example.com/x').'&goto=1');
        self::assertSelectorExists('input[name="goto"][value="1"]');
        $token = (string) $crawler->filter('form[action="/links/quick"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/links/quick', ['_token' => $token, 'url' => 'https://www.example.com/x', 'goto' => '1']);
        self::assertResponseRedirects('https://www.example.com/x', 302);
    }

    public function testQuickRejectsReservedCode(): void
    {
        $user = $this->createUser('user@example.com');
        $this->createDomain('go.example.com', $user);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/links/quick?url='.urlencode('https://www.example.com/x'));
        $token = (string) $crawler->filter('form[action="/links/quick"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/links/quick', ['_token' => $token, 'url' => 'https://www.example.com/x', 'code' => 'admin']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.errors', 'reserviert');
        self::assertNull($this->em()->getRepository(Link::class)->findOneBy(['code' => 'admin']));
    }
}

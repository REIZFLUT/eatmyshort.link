<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Link;
use Symfony\Component\DomCrawler\Crawler;

final class LinkBulkTest extends AppTestCase
{
    // --- Sorting ---------------------------------------------------------

    public function testSortByClicksAscendingAndDescending(): void
    {
        $user = $this->createUser('user@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $low = $this->createLink($user, $domain, 'niedrig');
        $mid = $this->createLink($user, $domain, 'mittel');
        $high = $this->createLink($user, $domain, 'hoch');
        $this->setClicks($low, 3);
        $this->setClicks($mid, 17);
        $this->setClicks($high, 99);
        $this->client->loginUser($user);

        $this->client->request('GET', '/links?sort=clicks&dir=asc');
        self::assertResponseIsSuccessful();
        self::assertSame(['niedrig', 'mittel', 'hoch'], $this->codes($this->client->getCrawler()));

        $this->client->request('GET', '/links?sort=clicks&dir=desc');
        self::assertResponseIsSuccessful();
        self::assertSame(['hoch', 'mittel', 'niedrig'], $this->codes($this->client->getCrawler()));
    }

    public function testUnknownSortAndDirectionFallBackToDefault(): void
    {
        $user = $this->createUser('user@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $this->createLink($user, $domain, 'a');
        $this->createLink($user, $domain, 'b');
        $this->createLink($user, $domain, 'c');
        $this->client->loginUser($user);

        $this->client->request('GET', '/links?sort=quatsch&dir=seite');
        self::assertResponseIsSuccessful('unknown sort values must not raise a 500');
        // Default order is created/desc; equal creation timestamps break the tie by id desc.
        self::assertSame(['c', 'b', 'a'], $this->codes($this->client->getCrawler()));
    }

    public function testSortHeadersKeepTheActiveFilters(): void
    {
        $user = $this->createUser('user@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $this->createLink($user, $domain, 'treffer', 'https://www.example.com/treffer');
        $this->createLink($user, $domain, 'anderer', 'https://www.example.com/anderer');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/links?q=treffer');
        self::assertResponseIsSuccessful();

        $href = $crawler->filter('th a.sort-link')->first()->link()->getUri();
        self::assertStringContainsString('q=treffer', $href, 'the search filter survives a header click');
        self::assertStringContainsString('sort=code', $href, 'the link column sorts by code');
    }

    // --- Status filter ---------------------------------------------------

    public function testStatusFilterShowsOnlyMatchingLinks(): void
    {
        $user = $this->createUser('user@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $this->createLink($user, $domain, 'laeuft');
        $this->createLink($user, $domain, 'aus')->setActive(false);
        $this->createLink($user, $domain, 'vorbei')->setExpiresAt(new \DateTimeImmutable('-1 day'));
        $this->createLink($user, $domain, 'bald')->setExpiresAt(new \DateTimeImmutable('+1 day'));
        $this->em()->flush();
        $this->client->loginUser($user);

        $this->client->request('GET', '/links?status=live&sort=code&dir=asc');
        self::assertSame(['bald', 'laeuft'], $this->codes($this->client->getCrawler()));

        $this->client->request('GET', '/links?status=inactive');
        self::assertSame(['aus'], $this->codes($this->client->getCrawler()));

        $this->client->request('GET', '/links?status=expired');
        self::assertSame(['vorbei'], $this->codes($this->client->getCrawler()));

        $crawler = $this->client->request('GET', '/links?status=kaputt');
        self::assertCount(4, $this->codes($crawler), 'an unknown status means all links');
        self::assertSelectorTextContains('.segmented a.active', 'Alle');
    }

    public function testLinksOnAnInactiveDomainAreNotLive(): void
    {
        $user = $this->createUser('user@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $this->createLink($user, $domain, 'still');
        $domain->setActive(false);
        $this->em()->flush();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/links?status=live');
        self::assertSame([], $this->codes($crawler));
        self::assertSelectorExists('.empty a[href="/links"]', 'the empty result offers to reset the filters');
    }

    public function testSortHeadersAndBulkKeepTheStatusFilter(): void
    {
        $user = $this->createUser('user@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $link = $this->createLink($user, $domain, 'aus');
        $link->setActive(false);
        $this->em()->flush();
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/links?status=inactive');
        self::assertStringContainsString('status=inactive', (string) $crawler->filter('th a.sort-link')->first()->attr('href'));
        $token = (string) $crawler->filter('#bulk-form input[name="_token"]')->attr('value');
        self::assertSame('inactive', $crawler->filter('#bulk-form input[name="return[status]"]')->attr('value'));

        $this->client->request('POST', '/links/bulk', [
            '_token' => $token,
            'action' => 'activate',
            'ids' => [(string) $link->getId()],
            'return' => ['status' => 'inactive'],
        ], server: ['HTTP_ORIGIN' => 'http://'.self::ADMIN_HOST]);
        self::assertResponseRedirects('/links?status=inactive');
    }

    // --- Bulk actions ----------------------------------------------------

    public function testBulkDeactivateSkipsForeignLinks(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob = $this->createUser('bob@example.com');
        $domain = $this->createDomain('go.example.com', $alice, $bob);
        $own = $this->createLink($alice, $domain, 'meins');
        $foreign = $this->createLink($bob, $domain, 'fremd');
        $this->client->loginUser($alice);
        $token = $this->bulkToken();

        $this->client->request('POST', '/links/bulk', [
            '_token' => $token,
            'action' => 'deactivate',
            'ids' => [(string) $own->getId(), (string) $foreign->getId()],
        ], server: ['HTTP_ORIGIN' => 'http://'.self::ADMIN_HOST]);
        self::assertResponseRedirects('/links');

        $crawler = $this->client->followRedirect();
        $flash = $crawler->filter('.flash')->text();
        self::assertStringContainsString('1 geändert', $flash);
        self::assertStringContainsString('1 übersprungen', $flash);

        self::assertFalse($this->fresh($own)->isActive(), 'the own link is deactivated');
        self::assertTrue($this->fresh($foreign)->isActive(), 'the foreign link is never touched');
    }

    public function testBulkDeleteRemovesOnlyOwnLinks(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob = $this->createUser('bob@example.com');
        $domain = $this->createDomain('go.example.com', $alice, $bob);
        $own = $this->createLink($alice, $domain, 'meins');
        $foreign = $this->createLink($bob, $domain, 'fremd');
        $ownId = (int) $own->getId();
        $foreignId = (int) $foreign->getId();
        $this->client->loginUser($alice);
        $token = $this->bulkToken();

        $this->client->request('POST', '/links/bulk', [
            '_token' => $token,
            'action' => 'delete',
            'ids' => [(string) $ownId, (string) $foreignId],
        ], server: ['HTTP_ORIGIN' => 'http://'.self::ADMIN_HOST]);
        self::assertResponseRedirects('/links');

        $crawler = $this->client->followRedirect();
        self::assertStringContainsString('1 geändert', $crawler->filter('.flash')->text());

        $this->em()->clear();
        self::assertNull($this->em()->find(Link::class, $ownId), 'the own link is gone');
        self::assertNotNull($this->em()->find(Link::class, $foreignId), 'the foreign link survives');
    }

    public function testBulkWithoutValidCsrfTokenIsRejected(): void
    {
        $user = $this->createUser('user@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $link = $this->createLink($user, $domain, 'geschuetzt');
        $this->client->loginUser($user);

        $this->client->request('POST', '/links/bulk', [
            '_token' => 'definitiv-falsch',
            'action' => 'deactivate',
            'ids' => [(string) $link->getId()],
        ], server: ['HTTP_ORIGIN' => 'http://'.self::ADMIN_HOST]);
        self::assertResponseStatusCodeSame(403);

        self::assertTrue($this->fresh($link)->isActive(), 'nothing changes without a valid token');
    }

    public function testBulkRejectsMoreThanTwoHundredIds(): void
    {
        $user = $this->createUser('user@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $link = $this->createLink($user, $domain, 'bleibt');
        $this->client->loginUser($user);
        $token = $this->bulkToken();

        $ids = array_map(static fn (int $id): string => (string) $id, range(1, 201));
        $this->client->request('POST', '/links/bulk', [
            '_token' => $token,
            'action' => 'deactivate',
            'ids' => $ids,
        ], server: ['HTTP_ORIGIN' => 'http://'.self::ADMIN_HOST]);
        self::assertResponseStatusCodeSame(422);

        self::assertTrue($this->fresh($link)->isActive(), 'a too-large selection changes nothing');
    }

    public function testBulkMoveSkipsCollidingCodes(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob = $this->createUser('bob@example.com');
        $source = $this->createDomain('one.example.com', $alice);
        $target = $this->createDomain('two.example.com', $alice, $bob);
        $movable = $this->createLink($alice, $source, 'umzug');
        $conflict = $this->createLink($alice, $source, 'doppelt');
        // Bob already occupies the code "doppelt" on the target domain.
        $this->createLink($bob, $target, 'doppelt');
        $this->client->loginUser($alice);
        $token = $this->bulkToken();

        $this->client->request('POST', '/links/bulk', [
            '_token' => $token,
            'action' => 'move',
            'domain' => (string) $target->getId(),
            'ids' => [(string) $movable->getId(), (string) $conflict->getId()],
        ], server: ['HTTP_ORIGIN' => 'http://'.self::ADMIN_HOST]);
        self::assertResponseRedirects('/links');

        $crawler = $this->client->followRedirect();
        $flash = $crawler->filter('.flash')->text();
        self::assertStringContainsString('1 geändert', $flash);
        self::assertStringContainsString('1 wegen Kürzel-Konflikt', $flash);

        self::assertSame($target->getId(), $this->fresh($movable)->getDomain()?->getId(), 'the free code moves');
        self::assertSame($source->getId(), $this->fresh($conflict)->getDomain()?->getId(), 'the colliding code stays put');
    }

    public function testNonAdminSeesBulkBarOnlyForOwnDomains(): void
    {
        $alice = $this->createUser('alice@example.com');
        $bob = $this->createUser('bob@example.com');
        $ownDomain = $this->createDomain('own.example.com', $alice);
        $this->createDomain('other.example.com', $bob);
        $this->createLink($alice, $ownDomain, 'eigener');
        $this->client->loginUser($alice);

        $crawler = $this->client->request('GET', '/links');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#bulk-form');
        self::assertSelectorExists('#bulk-form select[name="domain"]');
        self::assertCount(1, $crawler->filter('#bulk-form select[name="domain"] option'));
        self::assertSelectorTextContains('#bulk-form select[name="domain"] option', 'own.example.com');
        self::assertSelectorTextNotContains('#bulk-form', 'other.example.com');
    }

    // --- Helpers ---------------------------------------------------------

    /** @return list<string> codes in table row order */
    private function codes(Crawler $crawler): array
    {
        $codes = [];
        foreach ($crawler->filter('tbody .link-cell .short') as $node) {
            $text = trim($node->textContent);
            $codes[] = substr($text, strrpos($text, '/') + 1);
        }

        return $codes;
    }

    private function setClicks(Link $link, int $clicks): void
    {
        $this->em()->getConnection()->executeStatement('UPDATE link SET click_count = ? WHERE id = ?', [$clicks, $link->getId()]);
    }

    /** Reads the CSRF token from the rendered bulk form (also asserts the bar exists). */
    private function bulkToken(): string
    {
        $crawler = $this->client->request('GET', '/links');
        self::assertSelectorExists('#bulk-form');

        return (string) $crawler->filter('#bulk-form input[name="_token"]')->attr('value');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Plugin\Ui\AdminNavItem;
use App\Plugin\Ui\AdminWidget;
use App\Tests\EventListener\AdminUiPluginListener;
use App\Tests\EventListener\ApiPluginListener;
use App\Tests\EventListener\LinkRejectionPluginListener;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Plugin hooks on the admin UI: dashboard widgets and admin-navigation entries.
 */
final class AdminUiPluginHooksTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AdminUiPluginListener::reset();
        ApiPluginListener::reset();
        LinkRejectionPluginListener::reset();
    }

    protected function tearDown(): void
    {
        AdminUiPluginListener::reset();
        ApiPluginListener::reset();
        LinkRejectionPluginListener::reset();
        parent::tearDown();
    }

    public function testDashboardRendersAPluginWidgetAfterTheBuiltInCards(): void
    {
        AdminUiPluginListener::withWidgets(new AdminWidget('demo', 'Plugin Widget', '42', 'from a plugin'));
        $this->client->loginUser($this->createUser('dash@example.com'));

        $crawler = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $stat = self::widgetByLabel($crawler, 'Plugin Widget');
        self::assertSame('42', $stat->filter('.value')->text());
        self::assertStringContainsString('from a plugin', $stat->text());
        self::assertCount(0, $stat->filter('a'), 'no url set, so the card is not a link');

        // Rendered after the built-in cards: the plugin grid is the last block of the dashboard stack.
        $stack = $crawler->filter('main .container div.stack')->first();
        self::assertSame('stats', $stack->children()->last()->attr('class'));
    }

    public function testDashboardEventTellsPluginsWhoIsLooking(): void
    {
        $user = $this->createUser('dash@example.com');
        $this->client->loginUser($user);
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $event = AdminUiPluginListener::$lastDashboard;
        self::assertNotNull($event);
        self::assertSame($user->getId(), $event->getViewer()->getId());
        self::assertFalse($event->isViewerAdmin(), 'a normal user must not get installation-wide cards');

        $this->client->loginUser($this->createUser('root@example.com', admin: true));
        $this->client->request('GET', '/');
        self::assertTrue(AdminUiPluginListener::$lastDashboard?->isViewerAdmin());
    }

    public function testDashboardWidgetWithUrlBecomesALink(): void
    {
        AdminUiPluginListener::withWidgets(new AdminWidget('demo', 'Plugin Widget', '42', hint: 'from a plugin', url: 'https://example.com/plugin'));
        $this->client->loginUser($this->createUser('dash@example.com'));

        $crawler = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $stat = $crawler->filter('section.stats a.stat[href="https://example.com/plugin"]');
        self::assertCount(1, $stat);
        self::assertSame('42', $stat->filter('.value')->text());
        self::assertSame('Plugin Widget', $stat->filter('.label')->text());
    }

    public function testPluginNavEntryAppearsForAdminsOnly(): void
    {
        AdminUiPluginListener::withNavItems(new AdminNavItem('Plugin Admin', '/plugin/admin'));

        // Admins see the built-in admin points and, with them, the plugin entries.
        $this->client->loginUser($this->createUser('root@example.com', admin: true));
        $crawler = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $entry = $crawler->filter('nav.nav a[href="/plugin/admin"]');
        self::assertCount(1, $entry);
        self::assertSame('Plugin Admin', $entry->text());

        // A normal user gets no admin navigation at all, hence no plugin entry either.
        $this->client->loginUser($this->createUser('normal@example.com'));
        $crawler = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('nav.nav a[href="/plugin/admin"]'));
        self::assertCount(0, $crawler->filter('nav.nav a[href="/admin/users"]'), 'the built-in admin points are hidden too - the gate is the same');
    }

    /**
     * Addresses this test's own card by its label.
     *
     * The dashboard is not exclusively ours: the plugin test fixture under
     * tests/Fixtures/plugins contributes a widget of its own, and a real installation will
     * have more of them. Asserting "there is exactly one card" would make this test fail
     * whenever somebody else adds one - which is precisely what a plugin system is for.
     */
    private static function widgetByLabel(Crawler $crawler, string $label): Crawler
    {
        $cards = $crawler->filter('section.stats .stat');
        self::assertGreaterThan(0, $cards->count(), 'the plugin dashboard section was not rendered');

        foreach ($cards as $index => $node) {
            $card = $cards->eq($index);
            if ($label === trim($card->filter('.label')->text())) {
                return $card;
            }
        }

        self::fail(sprintf('No plugin widget labelled "%s" was rendered.', $label));
    }
}

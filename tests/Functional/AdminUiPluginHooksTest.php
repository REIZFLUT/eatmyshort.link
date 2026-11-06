<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Plugin\Ui\AdminNavItem;
use App\Plugin\Ui\AdminWidget;
use App\Tests\EventListener\AdminUiPluginListener;
use App\Tests\EventListener\ApiPluginListener;
use App\Tests\EventListener\LinkRejectionPluginListener;

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

        $stat = $crawler->filter('section.stats .stat');
        self::assertCount(1, $stat);
        self::assertSame('Plugin Widget', $stat->filter('.label')->text());
        self::assertSame('42', $stat->filter('.value')->text());
        self::assertStringContainsString('from a plugin', $stat->text());
        self::assertCount(0, $stat->filter('a'), 'no url set, so the card is not a link');

        // Rendered after the built-in cards: the plugin grid is the last block of the dashboard stack.
        $stack = $crawler->filter('main .container div.stack')->first();
        self::assertSame('stats', $stack->children()->last()->attr('class'));
    }

    public function testDashboardWidgetWithUrlBecomesALink(): void
    {
        AdminUiPluginListener::withWidgets(new AdminWidget('demo', 'Plugin Widget', '42', hint: 'from a plugin', url: 'https://example.com/plugin'));
        $this->client->loginUser($this->createUser('dash@example.com'));

        $crawler = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $stat = $crawler->filter('section.stats a.stat');
        self::assertCount(1, $stat);
        self::assertSame('https://example.com/plugin', $stat->attr('href'));
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
}

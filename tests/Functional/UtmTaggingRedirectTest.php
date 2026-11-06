<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\EventListener\UtmTaggingPluginListener;

/**
 * The utm-tagging plugin's behaviour in the real redirect flow: a campaign parameter set by
 * the plugin ends up in the response's Location header. The plugin is not loaded through the
 * whitelist (the test environment points APP_PLUGIN_DIR at the fixtures); the test service
 * {@see UtmTaggingPluginListener} drives the real UtmTagger and is inert until a test arms it.
 */
final class UtmTaggingRedirectTest extends EventTestCase
{
    private const string CHROME = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';

    protected function setUp(): void
    {
        parent::setUp();
        UtmTaggingPluginListener::reset();
    }

    protected function tearDown(): void
    {
        UtmTaggingPluginListener::reset();
        parent::tearDown();
    }

    public function testWithoutSettingsTheTargetIsUnchanged(): void
    {
        $owner = $this->createUser('owner@example.com');
        $this->createLink($owner, $this->createDomain('go.example.com'), 'pur', 'https://www.example.com/landing');

        $this->shortRequest('go.example.com', '/pur', ['User-Agent' => self::CHROME]);

        self::assertResponseRedirects('https://www.example.com/landing', 302);
    }

    public function testCampaignParametersReachTheLocationHeader(): void
    {
        $owner = $this->createUser('owner@example.com');
        $this->createLink($owner, $this->createDomain('go.example.com'), 'kampagne', 'https://www.example.com/landing');

        UtmTaggingPluginListener::withSettings(['source' => 'newsletter', 'medium' => 'email', 'campaign' => 'spring']);

        $this->shortRequest('go.example.com', '/kampagne', ['User-Agent' => self::CHROME]);

        self::assertResponseRedirects(
            'https://www.example.com/landing?utm_source=newsletter&utm_medium=email&utm_campaign=spring',
            302,
        );
        // The visitor still gets a normal redirect; the click is counted as before.
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
    }

    public function testAnExistingTargetParameterIsKeptUnlessOverwriting(): void
    {
        $owner = $this->createUser('owner@example.com');
        $this->createLink($owner, $this->createDomain('go.example.com'), 'alt', 'https://www.example.com/landing?utm_source=crm');

        UtmTaggingPluginListener::withSettings(['source' => 'newsletter']);
        $this->shortRequest('go.example.com', '/alt', ['User-Agent' => self::CHROME]);
        self::assertResponseRedirects('https://www.example.com/landing?utm_source=crm', 302);

        UtmTaggingPluginListener::withSettings(['source' => 'newsletter', 'overwrite' => true]);
        $this->shortRequest('go.example.com', '/alt', ['User-Agent' => self::CHROME]);
        self::assertResponseRedirects('https://www.example.com/landing?utm_source=newsletter', 302);
    }
}

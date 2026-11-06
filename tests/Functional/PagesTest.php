<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use OTPHP\TOTP;

/**
 * Every page renders for an admin (catches template errors), and the HTML carries the strict CSP.
 */
final class PagesTest extends AppTestCase
{
    public function testFooterLinksToTheRepositoryInsteadOfRepeatingTheName(): void
    {
        $this->client->loginUser($this->createUser('footer@example.com'));

        $crawler = $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $footer = $crawler->filter('footer.site');
        self::assertSame(1, $footer->filter('a[href="https://github.com/REIZFLUT/eatmyshort.link"]')->count());
        self::assertSame('noopener noreferrer', $footer->filter('a')->attr('rel'));
        self::assertStringContainsString('GPL-3.0', $footer->text());
        // The product name is rendered once (as the configured title), not twice.
        self::assertSame(1, substr_count($footer->text(), 'EatMyShort.Link'));
    }

    public function testAllPagesRender(): void
    {
        $admin = $this->createUser('root@example.com', admin: true);
        $domain = $this->createDomain('go.example.com');
        $link = $this->createLink($admin, $domain, 'seite');
        $this->shortRequest('go.example.com', '/seite', ['User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1']);
        $this->client->loginUser($this->fresh($admin));

        foreach ([
            '/', '/links', '/links?q=seite&domain='.$domain->getId(), '/links/new', '/links/'.$link->getId(), '/links/'.$link->getId().'?days=7',
            '/links/'.$link->getId().'/edit', '/profile', '/profile/security', '/profile/totp', '/profile/tokens',
            '/admin/users', '/admin/users/new', '/admin/users/'.$admin->getId(), '/admin/domains', '/admin/domains/new',
            '/admin/domains/'.$domain->getId(), '/admin/ip-rules', '/admin/settings', '/admin/stats?days=90',
        ] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful($path);
            self::assertStringContainsString("script-src 'self' 'nonce-", (string) $this->client->getResponse()->headers->get('Content-Security-Policy'), $path);
        }

        foreach (['/links/'.$link->getId().'/qr.svg' => 'image/svg+xml', '/links/'.$link->getId().'/qr.png' => 'image/png', '/links/'.$link->getId().'/export.csv' => 'text/csv; charset=utf-8', '/branding/theme.css' => 'text/css; charset=utf-8'] as $path => $type) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful($path);
            self::assertResponseHeaderSame('Content-Type', $type);
        }
    }

    public function testTotpSetupFlow(): void
    {
        $user = $this->createUser('ada@example.com');
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/profile/totp');
        $secret = str_replace(' ', '', $crawler->filter('code')->text());
        if ('' === $secret) {
            self::fail('The setup page shows no TOTP secret.');
        }
        $this->client->request('GET', '/profile/totp/qr.svg');
        self::assertResponseHeaderSame('Content-Type', 'image/svg+xml');
        $this->client->request('GET', '/profile/totp');

        $this->client->submitForm('Aktivieren', ['code' => '123456']);
        self::assertResponseStatusCodeSame(422);

        $this->client->submitForm('Aktivieren', ['code' => TOTP::createFromSecret($secret)->now()]);
        self::assertResponseRedirects('/profile/security');
        $crawler = $this->client->followRedirect();
        self::assertCount(10, $crawler->filter('.codes li'), 'backup codes are shown once');

        $fresh = $this->fresh($user);
        self::assertTrue($fresh->isTotpAuthenticationEnabled());
        self::assertSame($secret, $fresh->getTotpSecret());

        $this->client->request('GET', '/profile/security');
        self::assertSelectorNotExists('.codes');
    }

    public function testPasskeyRegistrationOptionsForLoggedInUser(): void
    {
        $user = $this->createUser('ada@example.com');
        $this->client->loginUser($user);

        // Sensitive actions need a fresh confirmation first (see ProfileSecurityTest).
        $this->client->request('GET', '/profile/security');
        $session = $this->client->getRequest()->getSession();
        $session->set('_sensitive_confirmed_at', time());
        $session->save();

        $this->client->request('POST', '/profile/passkeys/options', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseIsSuccessful();
        $options = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(self::ADMIN_HOST, $options['rp']['id']);
        self::assertSame('ada@example.com', $options['user']['name']);
        self::assertSame('required', $options['authenticatorSelection']['residentKey']);
        self::assertSame('required', $options['authenticatorSelection']['userVerification']);

        $this->client->request('POST', '/login/passkey/options', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseIsSuccessful();
        $request = json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(self::ADMIN_HOST, $request['rpId']);
        self::assertArrayHasKey('challenge', $request);
    }

    public function testFaviconFollowsTheAccentColour(): void
    {
        $this->client->request('GET', '/branding/favicon.svg');
        self::assertResponseHeaderSame('Content-Type', 'image/svg+xml');
        $svg = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('fill="#fed90f"', $svg, 'Simpsons yellow by default');
        self::assertStringContainsString('fill="'.\App\Branding\AccentPalette::DARK_INK.'"', $svg);

        static::getContainer()->get(\App\Settings\Settings::class)->save([\App\Settings\Settings::ACCENT => '#660099']);
        $this->client->request('GET', '/branding/favicon.svg');
        $svg = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('fill="#660099"', $svg);
        self::assertStringContainsString('fill="#ffffff"', $svg, 'white mouth on a dark accent');
    }

    public function testThemeCssDerivesReadableColoursFromLightAccent(): void
    {
        static::getContainer()->get(\App\Settings\Settings::class)->save([\App\Settings\Settings::ACCENT => '#ffff00']);

        $this->client->request('GET', '/branding/theme.css');
        $css = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('--accent:#ffff00;', $css);
        self::assertStringContainsString('--accent-ink:'.\App\Branding\AccentPalette::DARK_INK.';', $css);
        self::assertStringNotContainsString('--accent-text:#ffff00;}'."\n@media", $css, 'yellow is not used as text on light surfaces');
    }

    public function testRequireTwoFactorForAdminsRedirectsToSecurityPage(): void
    {
        $admin = $this->createUser('root@example.com', admin: true);
        static::getContainer()->get(\App\Settings\Settings::class)->save([\App\Settings\Settings::REQUIRE_2FA_ADMINS => true]);
        $this->client->loginUser($admin);

        $this->client->request('GET', '/links');
        self::assertResponseRedirects('/profile/security');
        $this->client->request('GET', '/profile/security');
        self::assertResponseIsSuccessful();
    }
}

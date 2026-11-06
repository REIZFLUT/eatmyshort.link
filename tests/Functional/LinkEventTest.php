<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Link;
use App\Tests\Support\EventTestState;

/**
 * The LinkPersistingEvent / LinkSavedEvent / LinkDeletedEvent contract, exercised through
 * the admin UI: rejections become form errors, the lifecycle events report what happened.
 */
final class LinkEventTest extends EventTestCase
{
    public function testSavedEventReportsCreatedThenUpdated(): void
    {
        $user = $this->createUser('user@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $this->client->loginUser($user);
        EventTestState::$recordLinkLifecycle = true;

        // Create.
        $this->submitNewLink($domain->getId(), 'https://www.example.com/lebenszyklus', 'lebenszyklus');
        self::assertResponseRedirects();

        $saved = EventTestState::savedLinks();
        $persisted = EventTestState::persistedLinks();
        self::assertCount(1, $saved);
        self::assertTrue($saved[0]['created'], 'a new link reports created=true');
        self::assertCount(1, $persisted);
        self::assertTrue($persisted[0]['isNew'], 'the persisting event knows it is new');

        $link = $this->em()->getRepository(Link::class)->findOneBy(['code' => 'lebenszyklus']);
        self::assertNotNull($link);

        // Update the same link.
        EventTestState::$savedLinks = [];
        EventTestState::$persistedLinks = [];
        $crawler = $this->client->request('GET', '/links/'.$link->getId().'/edit');
        $form = $crawler->selectButton('Speichern')->form();
        $values = $form->getPhpValues();
        $values['link']['title'] = 'geändert';
        $this->client->request('POST', $form->getUri(), $values, server: ['HTTP_ORIGIN' => 'http://'.self::ADMIN_HOST]);
        self::assertResponseRedirects();

        $saved = EventTestState::savedLinks();
        $persisted = EventTestState::persistedLinks();
        self::assertCount(1, $saved);
        self::assertFalse($saved[0]['created'], 'an update reports created=false');
        self::assertCount(1, $persisted);
        self::assertFalse($persisted[0]['isNew']);
    }

    public function testDeletedEventFiresWithTheLink(): void
    {
        $user = $this->createUser('user@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $link = $this->createLink($user, $domain, 'wegdamit');
        $this->client->loginUser($user);
        EventTestState::$recordLinkLifecycle = true;

        $crawler = $this->client->request('GET', '/links/'.$link->getId().'/edit');
        $this->client->submit($crawler->filter('form[data-confirm]')->form());
        self::assertResponseRedirects('/links');

        $deleted = EventTestState::deletedLinks();
        self::assertCount(1, $deleted);
        self::assertSame('wegdamit', $deleted[0]->getCode());
    }

    public function testRejectedLinkShowsThePluginMessageAndStoresNothing(): void
    {
        $user = $this->createUser('user@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $this->client->loginUser($user);
        EventTestState::$linkRejectionMessage = 'Richtlinie: dieses Ziel ist nicht erlaubt.';

        $before = $this->em()->getRepository(Link::class)->count([]);

        $this->submitNewLink($domain->getId(), 'https://www.example.com/abgelehnt', 'abgelehnt');

        self::assertResponseStatusCodeSame(422, 'a rejection must not become a 500');
        self::assertSelectorTextContains('.errors', 'Richtlinie: dieses Ziel ist nicht erlaubt.');
        self::assertSame($before, $this->em()->getRepository(Link::class)->count([]), 'nothing must be stored');
    }

    public function testRejectedEditKeepsTheStoredLinkUnchanged(): void
    {
        $user = $this->createUser('user@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $link = $this->createLink($user, $domain, 'bleibt', 'https://www.example.com/original');
        $this->client->loginUser($user);
        EventTestState::$linkRejectionMessage = 'Kein Update erlaubt.';

        $crawler = $this->client->request('GET', '/links/'.$link->getId().'/edit');
        $form = $crawler->selectButton('Speichern')->form();
        $values = $form->getPhpValues();
        $values['link']['targetUrl'] = 'https://www.example.com/anders';
        $this->client->request('POST', $form->getUri(), $values, server: ['HTTP_ORIGIN' => 'http://'.self::ADMIN_HOST]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.errors', 'Kein Update erlaubt.');
        self::assertSame('https://www.example.com/original', $this->fresh($link)->getTargetUrl(), 'the rejected update is not persisted');
    }

    private function submitNewLink(?int $domainId, string $target, string $code): void
    {
        $this->client->request('GET', '/links/new');
        $this->client->submitForm('Link anlegen', [
            'link[targetUrl]' => $target,
            'link[domain]' => (string) $domainId,
            'link[code]' => $code,
        ], serverParameters: ['HTTP_ORIGIN' => 'http://'.self::ADMIN_HOST]);
    }
}

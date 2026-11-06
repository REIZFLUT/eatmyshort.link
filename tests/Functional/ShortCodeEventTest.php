<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Link;
use App\Tests\Support\EventTestState;

/**
 * The ShortCodeGeneratingEvent contract: a plugin suggestion is used when it is free and
 * well formed, and silently falls back to a random code otherwise - never a collision.
 */
final class ShortCodeEventTest extends EventTestCase
{
    public function testSuggestedCodeIsUsed(): void
    {
        $user = $this->createUser('user@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $this->client->loginUser($user);
        EventTestState::$shortCode = 'sommer-aktion';

        $this->submitWithoutCode($domain->getId(), 'https://www.example.com/sommer');
        self::assertResponseRedirects();

        $link = $this->em()->getRepository(Link::class)->findOneBy(['targetUrl' => 'https://www.example.com/sommer']);
        self::assertNotNull($link);
        self::assertSame('sommer-aktion', $link->getCode());
    }

    public function testTakenSuggestionFallsBackToARandomCode(): void
    {
        $user = $this->createUser('user@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $this->createLink($user, $domain, 'belegt', 'https://www.example.com/belegt');
        $this->client->loginUser($user);
        EventTestState::$shortCode = 'belegt';

        $this->submitWithoutCode($domain->getId(), 'https://www.example.com/zweite');
        self::assertResponseRedirects();

        $link = $this->em()->getRepository(Link::class)->findOneBy(['targetUrl' => 'https://www.example.com/zweite']);
        self::assertNotNull($link);
        self::assertNotSame('belegt', $link->getCode(), 'no collision is allowed');
        self::assertMatchesRegularExpression('/^[23456789abcdefghjkmnpqrstuvwxyz]{6}$/', $link->getCode(), 'the random fallback was used');
    }

    public function testInvalidSuggestionFallsBackToARandomCode(): void
    {
        $user = $this->createUser('user@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $this->client->loginUser($user);
        EventTestState::$shortCode = 'admin';

        $this->submitWithoutCode($domain->getId(), 'https://www.example.com/reserviert');
        self::assertResponseRedirects();

        $link = $this->em()->getRepository(Link::class)->findOneBy(['targetUrl' => 'https://www.example.com/reserviert']);
        self::assertNotNull($link);
        self::assertNotSame('admin', $link->getCode());
        self::assertMatchesRegularExpression('/^[23456789abcdefghjkmnpqrstuvwxyz]{6}$/', $link->getCode());
    }

    private function submitWithoutCode(?int $domainId, string $target): void
    {
        $this->client->request('GET', '/links/new');
        $this->client->submitForm('Link anlegen', [
            'link[targetUrl]' => $target,
            'link[domain]' => (string) $domainId,
            'link[code]' => '',
        ], serverParameters: ['HTTP_ORIGIN' => 'http://'.self::ADMIN_HOST]);
    }
}

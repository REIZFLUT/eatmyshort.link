<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ApiToken;
use App\Entity\Link;
use App\Entity\User;

final class ApiTest extends AppTestCase
{
    public function testTokenIsRequired(): void
    {
        $this->client->request('GET', '/api/v1/links');
        self::assertResponseStatusCodeSame(401);

        $this->client->request('GET', '/api/v1/links', server: ['HTTP_AUTHORIZATION' => 'Bearer emsl_wrong']);
        self::assertResponseStatusCodeSame(401);
    }

    public function testCrudAndStats(): void
    {
        $user = $this->createUser('api@example.com');
        $this->createDomain('go.example.com', $user);
        $token = $this->token($user);

        $this->api('POST', '/api/v1/links', $token, [
            'target_url' => 'https://www.example.com/kampagne',
            'code' => 'Kampagne',
            'localisations' => ['en' => 'https://www.example.com/en/campaign'],
        ]);
        self::assertResponseStatusCodeSame(201);
        $created = $this->json()['data'];
        self::assertSame('https://go.example.com/kampagne', $created['short_url']);
        self::assertSame(['en' => 'https://www.example.com/en/campaign'], $created['localisations']);

        $this->api('PATCH', '/api/v1/links/'.$created['id'], $token, ['active' => false, 'title' => 'Herbst']);
        self::assertResponseIsSuccessful();
        self::assertFalse($this->json()['data']['active']);
        self::assertSame('Herbst', $this->json()['data']['title']);

        $this->api('GET', '/api/v1/links?q=kampagne', $token);
        self::assertSame(1, $this->json()['meta']['total']);

        $this->api('GET', '/api/v1/links/'.$created['id'].'/stats?days=7', $token);
        self::assertCount(7, $this->json()['data']['per_day']);

        $this->api('DELETE', '/api/v1/links/'.$created['id'], $token);
        self::assertResponseStatusCodeSame(204);
    }

    public function testValidationAndPermissions(): void
    {
        $user = $this->createUser('api@example.com');
        $other = $this->createUser('other@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $this->createDomain('privat.example.com');
        $foreignLink = $this->createLink($other, $domain, 'fremd');
        $token = $this->token($user);

        $this->api('POST', '/api/v1/links', $token, ['target_url' => 'ftp://example.com']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('target_url', $this->json()['violations'][0]['field']);

        $this->api('POST', '/api/v1/links', $token, ['target_url' => 'https://example.com', 'domain' => 'privat.example.com']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('domain', $this->json()['violations'][0]['field']);

        $this->api('GET', '/api/v1/links/'.$foreignLink->getId(), $token);
        self::assertResponseStatusCodeSame(403);

        $this->api('POST', '/api/v1/links', $token, 'kein json');
        self::assertResponseStatusCodeSame(400);
    }

    public function testExpiredTokenIsRejected(): void
    {
        $user = $this->createUser('api@example.com');
        [$token, $plain] = ApiToken::issue($user, 'alt', new \DateTimeImmutable('-1 day'));
        $this->em()->persist($token);
        $this->em()->flush();

        $this->api('GET', '/api/v1/domains', $plain);
        self::assertResponseStatusCodeSame(401);
    }

    public function testDuplicateLocalisationLanguagesAreRejected(): void
    {
        $user = $this->createUser('api@example.com');
        $this->createDomain('go.example.com', $user);
        $token = $this->token($user);

        $this->api('POST', '/api/v1/links', $token, [
            'target_url' => 'https://www.example.com/doppelt',
            'code' => 'doppelt',
            'localisations' => [
                ['language' => 'en', 'target_url' => 'https://www.example.com/en'],
                ['language' => 'EN', 'target_url' => 'https://www.example.com/en-2'],
            ],
        ]);

        self::assertResponseStatusCodeSame(422, 'a duplicate language must not become a database 500');
        $violations = $this->json()['violations'];
        self::assertSame('localisations', $violations[0]['field']);
        self::assertMatchesRegularExpression('/Sprache|language/i', (string) $violations[0]['message']);
        self::assertNull($this->em()->getRepository(Link::class)->findOneBy(['code' => 'doppelt']));
    }

    public function testLastUsedAtIsWrittenAtMostOncePerMinute(): void
    {
        $user = $this->createUser('api@example.com');
        $this->createDomain('go.example.com', $user);
        [$token, $plain] = ApiToken::issue($user, 'throttle');
        $this->em()->persist($token);
        $this->em()->flush();
        $tokenId = (int) $token->getId();

        $this->api('GET', '/api/v1/domains', $plain);
        self::assertResponseIsSuccessful();
        $this->em()->clear();
        $first = $this->em()->find(ApiToken::class, $tokenId);
        self::assertNotNull($first);
        $firstUsed = $first->getLastUsedAt();
        self::assertNotNull($firstUsed);

        // Inside the 60-second window: the next call must not write again.
        $backdated = $firstUsed->modify('-30 seconds');
        $this->em()->getConnection()->executeStatement(
            'UPDATE api_token SET last_used_at = ? WHERE id = ?',
            [$backdated->format('Y-m-d H:i:s'), $tokenId],
        );
        $this->em()->clear();

        $this->api('GET', '/api/v1/domains', $plain);
        self::assertResponseIsSuccessful();
        $this->em()->clear();
        $second = $this->em()->find(ApiToken::class, $tokenId);
        self::assertNotNull($second);
        self::assertNotNull($second->getLastUsedAt());
        self::assertSame($backdated->format('Y-m-d H:i:s'), $second->getLastUsedAt()->format('Y-m-d H:i:s'));
    }

    private function token(User $user): string
    {
        [$token, $plain] = ApiToken::issue($user, 'test');
        $this->em()->persist($token);
        $this->em()->flush();

        return $plain;
    }

    /** @param array<string, mixed>|string|null $body */
    private function api(string $method, string $uri, string $token, array|string|null $body = null): void
    {
        $this->client->request($method, $uri, server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ], content: \is_array($body) ? json_encode($body, JSON_THROW_ON_ERROR) : $body);
    }

    /** @return array<string, mixed> */
    private function json(): array
    {
        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
    }
}

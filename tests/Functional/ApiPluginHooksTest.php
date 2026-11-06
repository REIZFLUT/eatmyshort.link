<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\ApiToken;
use App\Entity\Link;
use App\Entity\User;
use App\Tests\EventListener\AdminUiPluginListener;
use App\Tests\EventListener\ApiPluginListener;
use App\Tests\EventListener\LinkRejectionPluginListener;

/**
 * Plugin hooks on the JSON API: ApiResponseEvent enrichment and the 422 for a rejected link.
 */
final class ApiPluginHooksTest extends AppTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ApiPluginListener::reset();
        LinkRejectionPluginListener::reset();
        AdminUiPluginListener::reset();
    }

    protected function tearDown(): void
    {
        ApiPluginListener::reset();
        LinkRejectionPluginListener::reset();
        AdminUiPluginListener::reset();
        parent::tearDown();
    }

    public function testPayloadsAreUnchangedWithoutAListener(): void
    {
        $user = $this->createUser('api@example.com');
        $this->createDomain('go.example.com', $user);
        $token = $this->token($user);

        $this->api('GET', '/api/v1/links', $token);
        self::assertResponseIsSuccessful();
        $payload = $this->json();
        self::assertArrayHasKey('data', $payload);
        self::assertArrayHasKey('meta', $payload);
        self::assertArrayNotHasKey(ApiPluginListener::MARKER, $payload, 'no plugin is armed, so no extra field appears');
        // The event is dispatched even with nothing listening - the operation is recorded.
        self::assertContains('links.list', ApiPluginListener::operations());
    }

    public function testPluginEnrichesListAndDetailResponses(): void
    {
        $user = $this->createUser('api@example.com');
        $domain = $this->createDomain('go.example.com', $user);
        $link = $this->createLink($user, $domain, 'marke');
        $token = $this->token($user);

        ApiPluginListener::arm();

        $this->api('POST', '/api/v1/links', $token, ['target_url' => 'https://www.example.com/neu', 'code' => 'neu']);
        self::assertResponseStatusCodeSame(201, 'the status code must not change');
        self::assertSame('yes', $this->json()[ApiPluginListener::MARKER]);

        $this->api('GET', '/api/v1/links', $token);
        self::assertResponseIsSuccessful();
        $list = $this->json();
        self::assertSame('yes', $list[ApiPluginListener::MARKER]);
        self::assertCount(2, $list['data'], 'the data itself is untouched');

        $this->api('GET', '/api/v1/links/'.$link->getId(), $token);
        self::assertResponseIsSuccessful();
        $detail = $this->json();
        self::assertSame('yes', $detail[ApiPluginListener::MARKER]);
        self::assertSame('marke', $detail['data']['code']);

        $operations = ApiPluginListener::operations();
        self::assertContains('links.create', $operations);
        self::assertContains('links.list', $operations);
        self::assertContains('links.show', $operations);
    }

    public function testRejectedLinkBecomesA422WithThePluginMessage(): void
    {
        $user = $this->createUser('api@example.com');
        $this->createDomain('go.example.com', $user);
        $token = $this->token($user);

        // A plugin refuses the write (LinkPersistingEvent); LinkManager turns that into a
        // LinkRejectedException, which the controller must answer as a 422 - never a 500.
        LinkRejectionPluginListener::rejectWith('Blocked by the test plugin.');

        $this->api('POST', '/api/v1/links', $token, ['target_url' => 'https://www.example.com/abgelehnt', 'code' => 'abgelehnt']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('Blocked by the test plugin.', $this->json()['error']);

        $this->em()->clear();
        self::assertNull($this->em()->getRepository(Link::class)->findOneBy(['code' => 'abgelehnt']), 'the rejected link must not be stored');
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

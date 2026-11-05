<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class HealthTest extends AppTestCase
{
    public function testHealthEndpointIsPublicAndReportsOk(): void
    {
        $this->client->request('GET', '/healthz');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertStringContainsString('no-store', (string) $this->client->getResponse()->headers->get('Cache-Control'));

        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('ok', $payload['status']);
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}([+-]\d{2}:\d{2}|Z)$/',
            (string) $payload['time'],
        );
    }
}

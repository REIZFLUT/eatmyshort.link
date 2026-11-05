<?php

declare(strict_types=1);

namespace App\Tests\Functional;

/**
 * bin/deploy.sh flips var/maintenance around the update; the app has to answer with 503
 * instead of serving old code against a half-migrated database.
 */
final class MaintenanceTest extends AppTestCase
{
    private string $flag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->flag = static::getContainer()->getParameter('kernel.project_dir').'/var/maintenance';
        self::assertFileDoesNotExist($this->flag, 'no deploy is running while the tests run');
    }

    protected function tearDown(): void
    {
        if (is_file($this->flag)) {
            unlink($this->flag);
        }
        parent::tearDown();
    }

    public function testMaintenanceFlagBlocksEveryRoute(): void
    {
        touch($this->flag);

        $this->client->request('GET', '/login');
        self::assertResponseStatusCodeSame(503);
        self::assertResponseHeaderSame('Retry-After', '60');
        self::assertResponseHeaderSame('Cache-Control', 'no-store, private');
        self::assertStringContainsString('Wartung', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/healthz');
        self::assertResponseStatusCodeSame(503, 'monitoring sees the maintenance window too');

        unlink($this->flag);
        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();
    }
}

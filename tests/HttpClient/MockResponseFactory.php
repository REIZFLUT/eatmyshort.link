<?php

declare(strict_types=1);

namespace App\Tests\HttpClient;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Test stand-in for every HTTP request. Configured as framework.http_client.mock_response_factory
 * in the test environment, so the whole suite runs without touching the network: any request a
 * service makes answers with a fixed HTML page carrying a recognisable title.
 */
final class MockResponseFactory
{
    public const string TITLE = 'Mock-Titel';

    /** @param array<string, mixed> $options */
    public function __invoke(string $method, string $url, array $options): MockResponse
    {
        $body = \sprintf('<html><head><title>%s</title></head><body>ok</body></html>', self::TITLE);

        return new MockResponse($body, [
            'response_headers' => ['content-type' => 'text/html; charset=utf-8'],
        ]);
    }
}

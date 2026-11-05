<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Link\LinkTitleFetcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class LinkTitleFetcherTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function unsafeUrls(): iterable
    {
        yield 'loopback ipv4' => ['http://127.0.0.1/x'];
        yield 'loopback ipv6' => ['http://[::1]/x'];
        yield 'link local metadata' => ['http://169.254.169.254/latest/meta-data'];
        yield 'private class a' => ['http://10.0.0.5/'];
        yield 'localhost name' => ['http://localhost/'];
        yield 'non http scheme' => ['file:///etc/passwd'];
    }

    #[DataProvider('unsafeUrls')]
    public function testUnsafeTargetsAreRejectedWithoutAnyRequest(string $url): void
    {
        $calls = 0;
        $client = new MockHttpClient(static function () use (&$calls): MockResponse {
            ++$calls;

            return new MockResponse('<html><title>should not happen</title></html>', [
                'response_headers' => ['content-type' => 'text/html'],
            ]);
        });

        $fetcher = new LinkTitleFetcher($client, new NullLogger());

        self::assertNull($fetcher->fetch($url));
        self::assertSame(0, $calls, 'the SSRF guard must reject before the HTTP client is used');
        self::assertSame(0, $client->getRequestsCount());
    }

    public function testOpenGraphTitleWinsAndEntitiesAreDecoded(): void
    {
        $html = '<html><head><title>Fallback</title>'
            .'<meta property="og:title" content="Tom &amp; Jerry &ndash; K&uuml;che">'
            .'</head><body></body></html>';
        $fetcher = $this->fetcherFor($html, 'text/html; charset=utf-8');

        self::assertSame('Tom & Jerry – Küche', $fetcher->fetch('http://93.184.216.34/seite'));
    }

    public function testTitleElementIsUsedWhenNoOpenGraphTitleExists(): void
    {
        $fetcher = $this->fetcherFor('<html><head><title>  Ein   schlichter Titel </title></head></html>');

        self::assertSame('Ein schlichter Titel', $fetcher->fetch('http://93.184.216.34/seite'));
    }

    public function testTitleIsShortenedTo255Characters(): void
    {
        $title = str_repeat('a', 300);
        $fetcher = $this->fetcherFor('<html><head><title>'.$title.'</title></head></html>');

        $result = $fetcher->fetch('http://93.184.216.34/seite');

        self::assertNotNull($result);
        self::assertSame(255, mb_strlen($result));
    }

    public function testEmptyTitleReturnsNull(): void
    {
        $fetcher = $this->fetcherFor('<html><head><title>   </title></head></html>');

        self::assertNull($fetcher->fetch('http://93.184.216.34/seite'));
    }

    public function testNonHtmlContentTypeReturnsNull(): void
    {
        $fetcher = $this->fetcherFor('{"title": "json"}', 'application/json');

        self::assertNull($fetcher->fetch('http://93.184.216.34/seite'));
    }

    public function testHttpErrorReturnsNull(): void
    {
        $client = new MockHttpClient(new MockResponse('kaputt', ['http_code' => 500]));
        $fetcher = new LinkTitleFetcher($client, new NullLogger());

        self::assertNull($fetcher->fetch('http://93.184.216.34/seite'));
    }

    public function testTransportExceptionReturnsNull(): void
    {
        $client = new MockHttpClient(static function (): never {
            throw new \RuntimeException('timeout');
        });
        $fetcher = new LinkTitleFetcher($client, new NullLogger());

        self::assertNull($fetcher->fetch('http://93.184.216.34/seite'));
    }

    private function fetcherFor(string $body, string $contentType = 'text/html; charset=utf-8'): LinkTitleFetcher
    {
        $client = new MockHttpClient(new MockResponse($body, [
            'response_headers' => ['content-type' => $contentType],
        ]));

        return new LinkTitleFetcher($client, new NullLogger());
    }
}

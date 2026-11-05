<?php

declare(strict_types=1);

namespace App\Link;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Fetches the page <title> (or og:title) of a target URL, the way YOURLS does when a
 * link is shortened.
 *
 * The fetcher never throws: DNS problems, SSRF rejections, timeouts, HTTP errors, wrong
 * content types and broken HTML all result in null so that link creation continues
 * undisturbed. Every request is guarded against SSRF *before* the connection: only
 * http/https to a host that resolves to strictly public addresses is allowed.
 */
final class LinkTitleFetcher
{
    private const int MAX_BYTES = 262144; // 256 KB
    private const int MAX_TITLE_LENGTH = 255;
    private const int MAX_REDIRECTS = 2;

    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function fetch(string $url): ?string
    {
        try {
            $current = $url;
            // Redirects are followed manually: the HTTP client would otherwise follow a
            // Location into a private address without asking this guard again.
            for ($hop = 0; $hop <= self::MAX_REDIRECTS; ++$hop) {
                if (!$this->isSafeTarget($current)) {
                    return null;
                }

                $response = $this->client->request('GET', $current, [
                    'timeout' => 3,
                    'max_duration' => 5,
                    'max_redirects' => 0,
                    'headers' => [
                        'User-Agent' => 'EatMyShort.Link Titelabruf',
                        'Accept' => 'text/html',
                    ],
                ]);

                $status = $response->getStatusCode();
                if (\in_array($status, [301, 302, 303, 307, 308], true)) {
                    $location = $response->getHeaders(false)['location'][0] ?? null;
                    $response->cancel();
                    if (!\is_string($location) || '' === $location) {
                        return null;
                    }
                    $next = $this->absoluteLocation($current, $location);
                    if (null === $next) {
                        return null;
                    }
                    $current = $next;
                    continue;
                }

                // Defence in depth against DNS rebinding: check the address we are really
                // connected to, not only the one that was resolved before the request.
                $primaryIp = $response->getInfo('primary_ip');
                if (\is_string($primaryIp) && '' !== $primaryIp && !$this->isPublicIp($primaryIp)) {
                    $response->cancel();

                    return null;
                }

                return $this->titleFromResponse($response);
            }

            return null;
        } catch (\Throwable $e) {
            $this->logger->debug('Titelabruf für "{url}" fehlgeschlagen: {message}', ['url' => $url, 'message' => $e->getMessage()]);

            return null;
        }
    }

    /** Reads and validates one (non-redirect) response. */
    private function titleFromResponse(ResponseInterface $response): ?string
    {
        $status = $response->getStatusCode();
        if ($status < 200 || $status >= 300) {
            $response->cancel();

            return null;
        }

        $contentType = mb_strtolower($response->getHeaders(false)['content-type'][0] ?? '');
        if (!str_contains($contentType, 'text/html') && !str_contains($contentType, 'application/xhtml+xml')) {
            $response->cancel();

            return null;
        }

        return $this->extractTitle($this->readLimited($response));
    }

    /** Turns a Location header into an absolute URL, or null when it is not http(s). */
    private function absoluteLocation(string $base, string $location): ?string
    {
        $location = trim($location);
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $parts = parse_url($base);
        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $scheme = (string) $parts['scheme'];
        $authority = $scheme.'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }
        if (str_starts_with($location, '/')) {
            return $authority.$location;
        }

        $path = (string) ($parts['path'] ?? '/');
        $directory = rtrim(substr($path, 0, (int) strrpos($path, '/') + 1), '/');

        return $authority.'/'.ltrim(ltrim($directory, '/').'/'.$location, '/');
    }

    /**
     * SSRF guard: only http/https, the host must be present and must not resolve to a
     * private or reserved address. A literal IP is validated directly; a hostname is
     * resolved and every returned address is checked. A host that does not resolve is
     * rejected.
     */
    private function isSafeTarget(string $url): bool
    {
        $parts = parse_url($url);
        if (!\is_array($parts)) {
            return false;
        }
        $scheme = mb_strtolower((string) ($parts['scheme'] ?? ''));
        if (!\in_array($scheme, ['http', 'https'], true)) {
            return false;
        }
        $host = trim((string) ($parts['host'] ?? ''), '[]');
        if ('' === $host) {
            return false;
        }

        if (false !== filter_var($host, \FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host);
        }

        // gethostbynamel() is IPv4 only; a host with a public A record and a private AAAA
        // record would otherwise slip through.
        $addresses = gethostbynamel($host) ?: [];
        foreach (dns_get_record($host, \DNS_AAAA) ?: [] as $record) {
            if (\is_string($record['ipv6'] ?? null)) {
                $addresses[] = $record['ipv6'];
            }
        }
        if ([] === $addresses) {
            return false;
        }
        foreach ($addresses as $address) {
            if (!$this->isPublicIp($address)) {
                return false;
            }
        }

        return true;
    }

    private function isPublicIp(string $ip): bool
    {
        return false !== filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE);
    }

    /** Reads at most MAX_BYTES of the body and aborts the transfer once the limit is hit. */
    private function readLimited(ResponseInterface $response): string
    {
        $content = '';
        foreach ($this->client->stream($response) as $chunk) {
            if ($chunk->isTimeout()) {
                continue;
            }
            $content .= $chunk->getContent();
            if (\strlen($content) >= self::MAX_BYTES) {
                $response->cancel();
                $content = substr($content, 0, self::MAX_BYTES);
                break;
            }
        }

        return $content;
    }

    private function extractTitle(string $html): ?string
    {
        if ('' === trim($html)) {
            return null;
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new \DOMDocument();
            // The XML prologue pins the parser to UTF-8; otherwise libxml assumes Latin-1
            // and mangles every multi-byte character in the title.
            $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html, \LIBXML_NOERROR | \LIBXML_NOWARNING | \LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        if (!$loaded) {
            return null;
        }

        $title = $this->metaContent($document, 'og:title') ?? $this->nodeText($document, 'title');
        if (null === $title) {
            return null;
        }

        $title = html_entity_decode($title, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $title = trim((string) preg_replace('/\s+/u', ' ', $title));
        if ('' === $title) {
            return null;
        }

        return mb_substr($title, 0, self::MAX_TITLE_LENGTH);
    }

    private function metaContent(\DOMDocument $document, string $property): ?string
    {
        foreach ($document->getElementsByTagName('meta') as $meta) {
            $name = mb_strtolower($meta->getAttribute('property'));
            if ('' === $name) {
                $name = mb_strtolower($meta->getAttribute('name'));
            }
            if ($name === $property && '' !== trim($meta->getAttribute('content'))) {
                return $meta->getAttribute('content');
            }
        }

        return null;
    }

    private function nodeText(\DOMDocument $document, string $tag): ?string
    {
        $node = $document->getElementsByTagName($tag)->item(0);
        if (!$node instanceof \DOMNode) {
            return null;
        }
        $text = trim($node->textContent);

        return '' === $text ? null : $text;
    }
}

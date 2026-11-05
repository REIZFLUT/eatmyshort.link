<?php

declare(strict_types=1);

namespace App\Tracking;

use DeviceDetector\Cache\PSR6Bridge;
use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Thin wrapper around matomo/device-detector. Its parsed regex lists are static data,
 * so they live in cache.system (APCu or opcache-backed PHP files): uncached, one parse
 * costs ~300 ms; from a file cache that is re-read on every request still far too much.
 */
final readonly class UserAgentInspector
{
    public function __construct(
        #[Autowire(service: 'cache.system')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @param array<string, string> $headers lowercase header names (for client hints)
     *
     * @return array{bot: bool, device: string, browser: ?string, os: ?string}
     */
    public function inspect(?string $userAgent, array $headers = []): array
    {
        if (null === $userAgent || '' === trim($userAgent)) {
            // No user agent at all is almost always a script.
            return ['bot' => true, 'device' => 'unknown', 'browser' => null, 'os' => null];
        }

        $detector = new DeviceDetector($userAgent, ClientHints::factory($headers));
        $detector->setCache(new PSR6Bridge($this->cache));
        $detector->discardBotInformation();
        $detector->parse();

        if ($detector->isBot()) {
            return ['bot' => true, 'device' => 'bot', 'browser' => null, 'os' => null];
        }

        $browser = $detector->getClient('name');
        $os = $detector->getOs('name');

        return [
            'bot' => false,
            'device' => '' !== $detector->getDeviceName() ? $detector->getDeviceName() : 'unknown',
            'browser' => \is_string($browser) && '' !== $browser && 'UNK' !== $browser ? $browser : null,
            'os' => \is_string($os) && '' !== $os && 'UNK' !== $os ? $os : null,
        ];
    }
}

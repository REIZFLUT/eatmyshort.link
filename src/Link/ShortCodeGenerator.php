<?php

declare(strict_types=1);

namespace App\Link;

use App\Entity\Domain;
use App\Event\Link\ShortCodeGeneratingEvent;
use App\Repository\LinkRepository;
use App\Settings\Settings;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Random codes use lowercase letters and digits without look-alikes (0/o, 1/l/i),
 * so they survive being read aloud or typed from print. Codes are case-insensitive.
 */
final class ShortCodeGenerator
{
    public const string ALPHABET = '23456789abcdefghjkmnpqrstuvwxyz';
    public const string PATTERN = '/^[a-z0-9](?:[a-z0-9_-]{0,63})$/';

    /** Paths that must never become codes (they are used by the app or commonly probed). */
    public const array RESERVED = [
        'admin', 'api', 'login', 'logout', 'register', 'profile', 'account', 'settings', 'assets', 'bundles',
        'static', 'favicon.ico', 'robots.txt', 'health', 'status', '2fa', 'reset', 'invite', 'well-known',
        'branding', 'links', 'link', 'dashboard', 'stats', 'qr', 'wp-admin', 'wp-login', 'xmlrpc',
    ];

    private const int MAX_ATTEMPTS = 20;

    public function __construct(
        private readonly LinkRepository $links,
        private readonly Settings $settings,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
    }

    public static function isReserved(string $code): bool
    {
        return \in_array(mb_strtolower($code), self::RESERVED, true) || str_starts_with($code, '_');
    }

    public static function random(int $length): string
    {
        $code = '';
        $max = \strlen(self::ALPHABET) - 1;
        for ($i = 0; $i < $length; ++$i) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return $code;
    }

    /** Unused random code for the domain; grows by one character after repeated collisions. */
    public function generate(Domain $domain): string
    {
        $length = $this->settings->getInt(Settings::CODE_LENGTH);

        // A plugin may suggest a code for this call (readable slug, campaign prefix). The
        // suggestion is only a hint: it has to pass the same format rules the validator
        // enforces and must still be free on the domain, otherwise generation falls back to
        // a random code. That way a plugin can never produce a collision or break a rule.
        $generating = new ShortCodeGeneratingEvent($domain, $length);
        $this->dispatcher->dispatch($generating);
        $suggested = $generating->getShortCode();
        if (null !== $suggested) {
            $suggested = mb_strtolower(trim($suggested, " \t\n\r\0\x0B/"));
            if (1 === preg_match(self::PATTERN, $suggested) && !self::isReserved($suggested) && !$this->links->codeExists($domain, $suggested)) {
                return $suggested;
            }
        }

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            $code = self::random($length + intdiv($attempt - 1, 5));
            if (!self::isReserved($code) && !$this->links->codeExists($domain, $code)) {
                return $code;
            }
        }

        throw new \RuntimeException('Could not find a free short code.');
    }
}

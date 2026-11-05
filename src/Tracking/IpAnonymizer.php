<?php

declare(strict_types=1);

namespace App\Tracking;

/**
 * Cuts addresses down to the network: IPv4 /24, IPv6 /48.
 */
final class IpAnonymizer
{
    public static function anonymize(?string $ip): ?string
    {
        if (null === $ip || false === filter_var($ip, \FILTER_VALIDATE_IP)) {
            return null;
        }
        $packed = inet_pton($ip);
        if (false === $packed) {
            return null;
        }

        $keep = 4 === \strlen($packed) ? 3 : 6;
        $masked = substr($packed, 0, $keep).str_repeat("\0", \strlen($packed) - $keep);

        return inet_ntop($masked) ?: null;
    }
}

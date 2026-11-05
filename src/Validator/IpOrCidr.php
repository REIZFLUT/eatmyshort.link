<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * A single IPv4/IPv6 address or a CIDR range such as 10.0.0.0/8 or 2001:db8::/32.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class IpOrCidr extends Constraint
{
    public string $message = 'ip.invalid';
}

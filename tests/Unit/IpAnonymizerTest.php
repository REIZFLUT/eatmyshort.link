<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Tracking\IpAnonymizer;
use PHPUnit\Framework\TestCase;

final class IpAnonymizerTest extends TestCase
{
    public function testIpv4KeepsSlash24(): void
    {
        self::assertSame('203.0.113.0', IpAnonymizer::anonymize('203.0.113.77'));
    }

    public function testIpv6KeepsSlash48(): void
    {
        self::assertSame('2001:db8:abcd::', IpAnonymizer::anonymize('2001:db8:abcd:12:3456:789a:bcde:f012'));
    }

    public function testInvalidInput(): void
    {
        self::assertNull(IpAnonymizer::anonymize(null));
        self::assertNull(IpAnonymizer::anonymize('not-an-ip'));
    }
}

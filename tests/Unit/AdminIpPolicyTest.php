<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Security\AdminIpPolicy;
use App\Validator\IpOrCidrValidator;
use PHPUnit\Framework\TestCase;

final class AdminIpPolicyTest extends TestCase
{
    public function testEmptyRulesAllowEverybody(): void
    {
        self::assertTrue(AdminIpPolicy::allowsWith('198.51.100.1', []));
        self::assertTrue(AdminIpPolicy::allowsWith(null, []));
    }

    public function testCidrMatching(): void
    {
        $rules = ['10.0.0.0/8', '2001:db8::/32', '192.0.2.7'];

        self::assertTrue(AdminIpPolicy::allowsWith('10.20.30.40', $rules));
        self::assertTrue(AdminIpPolicy::allowsWith('2001:db8:1::5', $rules));
        self::assertTrue(AdminIpPolicy::allowsWith('192.0.2.7', $rules));
        self::assertFalse(AdminIpPolicy::allowsWith('192.0.2.8', $rules));
        self::assertFalse(AdminIpPolicy::allowsWith('2001:db9::1', $rules));
        self::assertFalse(AdminIpPolicy::allowsWith(null, $rules));
    }

    public function testRuleSyntax(): void
    {
        foreach (['10.0.0.1', '10.0.0.0/8', '::1', '2001:db8::/32', '0.0.0.0/0'] as $valid) {
            self::assertTrue(IpOrCidrValidator::isValid($valid), $valid);
        }
        foreach (['10.0.0.0/33', '2001:db8::/129', '10.0.0', 'localhost', '10.0.0.0/x', ''] as $invalid) {
            self::assertFalse(IpOrCidrValidator::isValid($invalid), $invalid);
        }
    }
}

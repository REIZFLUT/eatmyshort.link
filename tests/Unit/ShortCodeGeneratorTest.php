<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Link\ShortCodeGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ShortCodeGeneratorTest extends TestCase
{
    public function testRandomCodesUseUnambiguousAlphabet(): void
    {
        for ($i = 0; $i < 200; ++$i) {
            $code = ShortCodeGenerator::random(8);
            self::assertSame(8, \strlen($code));
            self::assertMatchesRegularExpression('/^[23456789abcdefghjkmnpqrstuvwxyz]+$/', $code);
            self::assertMatchesRegularExpression(ShortCodeGenerator::PATTERN, $code);
        }
    }

    /** @return iterable<array{string, bool}> */
    public static function codes(): iterable
    {
        yield ['sommer', true];
        yield ['sommer-2026_a', true];
        yield ['a', true];
        yield ['-start', false];
        yield ['Groß', false];
        yield ['mit leer', false];
        yield ['a/b', false];
        yield [str_repeat('a', 65), false];
    }

    #[DataProvider('codes')]
    public function testPattern(string $code, bool $valid): void
    {
        self::assertSame($valid, 1 === preg_match(ShortCodeGenerator::PATTERN, $code));
    }

    public function testReservedWords(): void
    {
        self::assertTrue(ShortCodeGenerator::isReserved('admin'));
        self::assertTrue(ShortCodeGenerator::isReserved('API'));
        self::assertTrue(ShortCodeGenerator::isReserved('_wdt'));
        self::assertFalse(ShortCodeGenerator::isReserved('sommer'));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Branding\AccentPalette;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AccentPaletteTest extends TestCase
{
    public function testWcagReferenceValues(): void
    {
        self::assertEqualsWithDelta(21.0, AccentPalette::contrast('#000000', '#ffffff'), 0.001);
        self::assertEqualsWithDelta(1.0, AccentPalette::luminance('#FFFFFF'), 0.0001);
        self::assertEqualsWithDelta(4.48, AccentPalette::contrast('#777777', '#ffffff'), 0.01);
    }

    public function testDefaultSimpsonsYellowGetsDarkInk(): void
    {
        $palette = AccentPalette::fromHex((string) \App\Settings\Settings::DEFAULTS[\App\Settings\Settings::ACCENT]);

        self::assertSame('#fed90f', $palette->accent);
        self::assertSame(AccentPalette::DARK_INK, $palette->ink);
        self::assertSame('#fed90f', $palette->textOnDark);
    }

    public function testPurpleKeepsWhiteInk(): void
    {
        $palette = AccentPalette::fromHex('#660099');

        self::assertSame(AccentPalette::LIGHT_INK, $palette->ink);
        self::assertSame('#660099', $palette->textOnLight, 'already readable on light surfaces');
        self::assertNotSame('#660099', $palette->textOnDark, 'too dark on dark surfaces, gets lightened');
    }

    public function testLightAccentGetsDarkInkAndDarkenedText(): void
    {
        $palette = AccentPalette::fromHex('#FFFF55');

        self::assertSame(AccentPalette::DARK_INK, $palette->ink);
        self::assertNotSame('#ffff55', $palette->textOnLight);
        self::assertSame('#ffff55', $palette->textOnDark);
    }

    /** @return iterable<array{string}> */
    public static function colours(): iterable
    {
        foreach (['#000000', '#ffffff', '#808080', '#ff0000', '#00ff00', '#0000ff', '#ffcc00', '#00a3e0', '#e30613', '#1f7a4c', '#f5f5dc', '#2b2b2b'] as $hex) {
            yield $hex => [$hex];
        }
    }

    #[DataProvider('colours')]
    public function testEveryAccentYieldsReadableColours(string $hex): void
    {
        $palette = AccentPalette::fromHex($hex);

        $bestInk = max(AccentPalette::contrast($hex, AccentPalette::LIGHT_INK), AccentPalette::contrast($hex, AccentPalette::DARK_INK));
        self::assertEqualsWithDelta($bestInk, AccentPalette::contrast($hex, $palette->ink), 0.0001, 'ink is the better of black and white');
        self::assertGreaterThanOrEqual(AccentPalette::MIN_TEXT_CONTRAST, AccentPalette::contrast($palette->textOnLight, AccentPalette::LIGHT_SURFACE));
        self::assertGreaterThanOrEqual(AccentPalette::MIN_TEXT_CONTRAST, AccentPalette::contrast($palette->textOnLight, '#ffffff'));
        self::assertGreaterThanOrEqual(AccentPalette::MIN_TEXT_CONTRAST, AccentPalette::contrast($palette->textOnDark, AccentPalette::DARK_SURFACE));
        self::assertGreaterThanOrEqual(AccentPalette::MIN_TEXT_CONTRAST, AccentPalette::contrast($palette->textOnDark, '#131217'));
    }

    public function testRejectsInvalidColours(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        AccentPalette::fromHex('yellow');
    }
}

<?php

declare(strict_types=1);

namespace App\Branding;

/**
 * Derives readable colours from the admin's accent colour (WCAG 2 contrast):
 *
 * - ink: text/icons on an accent background (buttons) – black-ish or white,
 *   whichever contrasts more with the accent;
 * - textOnLight / textOnDark: the accent used as text colour (links, active nav),
 *   darkened resp. lightened just enough for 4.5:1 on every surface of that theme.
 *
 * The surface colours mirror the tokens in assets/styles/app.css.
 */
final readonly class AccentPalette
{
    public const string DARK_INK = '#1d1b22';
    public const string LIGHT_INK = '#ffffff';
    /** Darkest light-theme surface (--surface-2); the others contrast even more. */
    public const string LIGHT_SURFACE = '#f1f0f4';
    /** Lightest dark-theme surface (--surface-2). */
    public const string DARK_SURFACE = '#24222b';
    public const float MIN_TEXT_CONTRAST = 4.5;

    private function __construct(
        public string $accent,
        public string $ink,
        public string $textOnLight,
        public string $textOnDark,
    ) {
    }

    public static function fromHex(string $accent): self
    {
        $accent = self::normalize($accent);
        $ink = self::contrast($accent, self::LIGHT_INK) >= self::contrast($accent, self::DARK_INK) ? self::LIGHT_INK : self::DARK_INK;

        return new self(
            $accent,
            $ink,
            self::readableOn($accent, self::LIGHT_SURFACE, '#000000'),
            self::readableOn($accent, self::DARK_SURFACE, '#ffffff'),
        );
    }

    /** WCAG 2 contrast ratio, 1 to 21. */
    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /** WCAG 2 relative luminance of an sRGB colour. */
    public static function luminance(string $hex): float
    {
        [$r, $g, $b] = array_map(static function (int $channel): float {
            $c = $channel / 255;

            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    /** Mixes the colour towards $towards in small steps until it reaches 4.5:1 on $surface. */
    private static function readableOn(string $color, string $surface, string $towards): string
    {
        $from = self::rgb($color);
        $to = self::rgb($towards);
        for ($step = 0; $step <= 20; ++$step) {
            $t = $step / 20;
            $mixed = self::hex(array_map(
                static fn (int $a, int $b): int => (int) round($a + ($b - $a) * $t),
                $from,
                $to,
            ));
            if (self::contrast($mixed, $surface) >= self::MIN_TEXT_CONTRAST) {
                return $mixed;
            }
        }

        return $towards;
    }

    private static function normalize(string $hex): string
    {
        if (1 !== preg_match('/^#?([0-9a-f]{6})$/i', trim($hex), $m)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a #rrggbb colour.', $hex));
        }

        return '#'.strtolower($m[1]);
    }

    /** @return array{int, int, int} */
    private static function rgb(string $hex): array
    {
        $hex = substr(self::normalize($hex), 1);

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }

    /** @param list<int> $rgb */
    private static function hex(array $rgb): string
    {
        return vsprintf('#%02x%02x%02x', $rgb);
    }
}

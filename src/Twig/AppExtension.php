<?php

declare(strict_types=1);

namespace App\Twig;

use App\Domain\HostRegistry;
use App\EventListener\SecurityHeadersListener;
use App\Settings\Settings;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Intl\Countries;
use Symfony\Component\Intl\Exception\MissingResourceException;
use Symfony\Component\Intl\Languages;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

final readonly class AppExtension
{
    public function __construct(
        private RequestStack $requestStack,
        private Settings $settings,
        private HostRegistry $hosts,
    ) {
    }

    #[AsTwigFunction('csp_nonce')]
    public function cspNonce(): string
    {
        $request = $this->requestStack->getMainRequest();

        return null === $request ? '' : SecurityHeadersListener::nonce($request);
    }

    #[AsTwigFunction('setting')]
    public function setting(string $key): ?string
    {
        return $this->settings->get($key);
    }

    #[AsTwigFunction('app_title')]
    public function appTitle(): string
    {
        return $this->settings->getString(Settings::TITLE);
    }

    #[AsTwigFunction('has_custom_logo')]
    public function hasCustomLogo(): bool
    {
        return null !== $this->settings->get(Settings::LOGO);
    }

    #[AsTwigFunction('primary_admin_host')]
    public function primaryAdminHost(): string
    {
        return $this->hosts->getPrimaryAdminHost();
    }

    #[AsTwigFilter('country_name')]
    public function countryName(?string $code, ?string $locale = null): string
    {
        if (null === $code || '' === $code) {
            return '–';
        }
        try {
            return Countries::getName(strtoupper($code), $locale);
        } catch (MissingResourceException) {
            return strtoupper($code);
        }
    }

    #[AsTwigFilter('language_name')]
    public function languageName(?string $code, ?string $locale = null): string
    {
        if (null === $code || '' === $code) {
            return '–';
        }
        try {
            return Languages::getName(str_replace('-', '_', $code), $locale);
        } catch (MissingResourceException) {
            return $code;
        }
    }

    /**
     * Bar chart as plain SVG markup (no JS, no inline styles; colours via CSS classes).
     *
     * @param list<array{day: \DateTimeImmutable, clicks: int, visitors: int}> $series
     */
    #[AsTwigFunction('bar_chart', isSafe: ['html'])]
    public function barChart(array $series, string $label = ''): string
    {
        $width = 720;
        $height = 180;
        $padTop = 12;
        $padBottom = 22;
        $count = max(1, \count($series));
        $max = max(1, ...array_map(static fn (array $p): int => $p['clicks'], $series ?: [['clicks' => 0]]));
        $slot = $width / $count;
        $barWidth = max(1.0, $slot * 0.72);
        $chartHeight = $height - $padTop - $padBottom;

        $bars = '';
        foreach ($series as $i => $point) {
            $h = $point['clicks'] > 0 ? max(2.0, $point['clicks'] / $max * $chartHeight) : 0.0;
            $x = $i * $slot + ($slot - $barWidth) / 2;
            $y = $padTop + $chartHeight - $h;
            $title = htmlspecialchars(\sprintf('%s: %d / %d', $point['day']->format('d.m.Y'), $point['clicks'], $point['visitors']), \ENT_QUOTES);
            $bars .= \sprintf(
                '<g class="chart-bar"><rect class="chart-hit" x="%.2f" y="%d" width="%.2f" height="%d"/><rect class="chart-fill" x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="1.5"/><title>%s</title></g>',
                $i * $slot, $padTop, $slot, $chartHeight, $x, $y, $barWidth, $h, $title,
            );
        }

        $labels = '';
        $step = max(1, (int) ceil($count / 8));
        foreach ($series as $i => $point) {
            if (0 === $i % $step) {
                $labels .= \sprintf('<text class="chart-label" x="%.2f" y="%d" text-anchor="middle">%s</text>', $i * $slot + $slot / 2, $height - 6, $point['day']->format('d.m.'));
            }
        }

        return \sprintf(
            '<svg class="chart" viewBox="0 0 %d %d" role="img" aria-label="%s"><line class="chart-axis" x1="0" y1="%d" x2="%d" y2="%d"/><text class="chart-label" x="2" y="%d">%d</text>%s%s</svg>',
            $width, $height, htmlspecialchars($label, \ENT_QUOTES), $padTop + $chartHeight, $width, $padTop + $chartHeight, $padTop + 2, $max, $bars, $labels,
        );
    }
}

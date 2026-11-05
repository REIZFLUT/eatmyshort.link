<?php

declare(strict_types=1);

namespace App\Link;

/**
 * Picks the best of the available language tags for an Accept-Language header.
 * Per requested tag (by q-value): exact match, then its primary language,
 * then any regional variant of that primary language ("de" matches "de-at").
 */
final class AcceptLanguageMatcher
{
    /**
     * @param list<string> $available lowercase tags like "en", "de-at"
     */
    public static function bestMatch(?string $header, array $available): ?string
    {
        if (null === $header || '' === trim($header) || [] === $available) {
            return null;
        }

        foreach (self::parse($header) as $tag) {
            if (\in_array($tag, $available, true)) {
                return $tag;
            }
            $primary = explode('-', $tag, 2)[0];
            if (\in_array($primary, $available, true)) {
                return $primary;
            }
            foreach ($available as $candidate) {
                if (str_starts_with($candidate, $primary.'-')) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /** First requested language's primary subtag, for statistics ("de-DE,de;q=0.9" -> "de"). */
    public static function primaryLanguage(?string $header): ?string
    {
        $tags = null === $header ? [] : self::parse($header);

        return [] === $tags ? null : explode('-', $tags[0], 2)[0];
    }

    /** @return list<string> lowercase tags ordered by quality, q=0 and "*" removed */
    public static function parse(string $header): array
    {
        $weighted = [];
        foreach (explode(',', $header) as $index => $part) {
            $pieces = explode(';', trim($part));
            $tag = str_replace('_', '-', strtolower(trim($pieces[0])));
            if ('' === $tag || '*' === $tag || !preg_match('/^[a-z]{1,8}(-[a-z0-9]{1,8})*$/', $tag)) {
                continue;
            }
            $q = 1.0;
            foreach (\array_slice($pieces, 1) as $param) {
                if (preg_match('/^\s*q\s*=\s*([01](?:\.\d{0,3})?)\s*$/i', $param, $m)) {
                    $q = (float) $m[1];
                }
            }
            if ($q > 0) {
                $weighted[] = [$tag, $q, $index];
            }
        }
        usort($weighted, static fn (array $a, array $b): int => [$b[1], $a[2]] <=> [$a[1], $b[2]]);

        return array_values(array_unique(array_column($weighted, 0)));
    }
}

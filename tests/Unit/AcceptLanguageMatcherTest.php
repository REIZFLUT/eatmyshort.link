<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Link\AcceptLanguageMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AcceptLanguageMatcherTest extends TestCase
{
    /** @return iterable<string, array{?string, list<string>, ?string}> */
    public static function cases(): iterable
    {
        yield 'exact' => ['en-US,en;q=0.9', ['de', 'en-us'], 'en-us'];
        yield 'primary of regional request' => ['de-AT,de;q=0.8', ['de', 'en'], 'de'];
        yield 'regional variant of primary request' => ['de', ['en', 'de-ch'], 'de-ch'];
        yield 'quality order wins' => ['fr;q=0.4,en;q=0.9', ['fr', 'en'], 'en'];
        yield 'order breaks ties' => ['fr,en', ['en', 'fr'], 'fr'];
        yield 'q=0 is excluded' => ['en;q=0,de;q=0.5', ['en'], null];
        yield 'nothing matches' => ['ja,zh', ['de', 'en'], null];
        yield 'empty header' => ['', ['de'], null];
        yield 'missing header' => [null, ['de'], null];
        yield 'wildcard ignored' => ['*', ['de'], null];
        yield 'garbage ignored' => ['<script>,en', ['en'], 'en'];
    }

    /** @param list<string> $available */
    #[DataProvider('cases')]
    public function testBestMatch(?string $header, array $available, ?string $expected): void
    {
        self::assertSame($expected, AcceptLanguageMatcher::bestMatch($header, $available));
    }

    public function testPrimaryLanguage(): void
    {
        self::assertSame('de', AcceptLanguageMatcher::primaryLanguage('de-DE,de;q=0.9,en;q=0.8'));
        self::assertSame('en', AcceptLanguageMatcher::primaryLanguage('de;q=0.5,en-GB'));
        self::assertNull(AcceptLanguageMatcher::primaryLanguage(null));
    }
}

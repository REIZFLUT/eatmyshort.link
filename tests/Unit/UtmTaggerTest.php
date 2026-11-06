<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Plugin\UtmTagging\UtmTagger;

final class UtmTaggerTest extends TestCase
{
    public function testWithoutConfiguredValuesNothingIsAdded(): void
    {
        $tagger = new UtmTagger([]);

        self::assertSame('https://www.example.com/landing?a=1', $tagger->tag('https://www.example.com/landing?a=1'));
    }

    public function testConfiguredParametersAreAppended(): void
    {
        $tagger = new UtmTagger(['source' => 'newsletter', 'medium' => 'email']);

        self::assertSame(
            'https://www.example.com/landing?utm_source=newsletter&utm_medium=email',
            $tagger->tag('https://www.example.com/landing'),
        );
    }

    public function testAppendingKeepsTheExistingQueryOrderAndPath(): void
    {
        $tagger = new UtmTagger(['campaign' => 'spring']);

        self::assertSame(
            'https://www.example.com/path?b=2&a=1&utm_campaign=spring',
            $tagger->tag('https://www.example.com/path?b=2&a=1'),
        );
    }

    public function testExistingParameterIsKeptWithoutOverwrite(): void
    {
        $tagger = new UtmTagger(['source' => 'plugin']);

        self::assertSame(
            'https://www.example.com/?utm_source=original',
            $tagger->tag('https://www.example.com/?utm_source=original'),
        );
    }

    public function testOverwriteReplacesAnExistingParameterInPlace(): void
    {
        $tagger = new UtmTagger(['source' => 'plugin', 'overwrite' => true]);

        self::assertSame(
            'https://www.example.com/?b=2&utm_source=plugin&a=1',
            $tagger->tag('https://www.example.com/?b=2&utm_source=old&a=1'),
        );
    }

    public function testFragmentStaysAtTheEnd(): void
    {
        $tagger = new UtmTagger(['campaign' => 'spring']);

        self::assertSame(
            'https://www.example.com/?a=1&utm_campaign=spring#details',
            $tagger->tag('https://www.example.com/?a=1#details'),
        );
    }

    public function testValuesAreUrlEncoded(): void
    {
        $tagger = new UtmTagger(['campaign' => 'spring launch']);

        self::assertSame(
            'https://www.example.com/?utm_campaign=spring%20launch',
            $tagger->tag('https://www.example.com/'),
        );
    }

    public function testEmptyOrNonStringValuesAreIgnored(): void
    {
        $tagger = new UtmTagger(['source' => '   ', 'medium' => '', 'campaign' => 42]);

        self::assertSame('https://www.example.com/', $tagger->tag('https://www.example.com/'));
    }

    public function testNonHttpTargetsAreLeftUntouched(): void
    {
        $tagger = new UtmTagger(['source' => 'newsletter']);

        self::assertSame('mailto:team@example.com', $tagger->tag('mailto:team@example.com'));
        self::assertSame('ftp://files.example.com/x', $tagger->tag('ftp://files.example.com/x'));
    }

    public function testUnparsableTargetsAreLeftUntouched(): void
    {
        $tagger = new UtmTagger(['source' => 'newsletter']);

        self::assertSame('https://', $tagger->tag('https://'));
        self::assertSame('/relative/path', $tagger->tag('/relative/path'));
    }
}

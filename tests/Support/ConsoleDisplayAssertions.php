<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * Asserts on SymfonyStyle output independently of where it wraps.
 *
 * Blocks such as $io->warning() wrap at the terminal width (COLUMNS, at most 120 columns) and
 * pad every line, so the position of a break depends on the machine and on the length of
 * temporary paths. A phrase may be split at a space ("not ⏎ listed") and a long path may be
 * cut in the middle of a word. The needle is therefore looked up in two flattened variants:
 * line breaks joined with one space (phrases) and joined with nothing (cut words, paths).
 */
trait ConsoleDisplayAssertions
{
    public static function assertDisplayContains(string $needle, string $display, string $message = ''): void
    {
        $spaced = (string) preg_replace('/[ \t]*\R[ \t]*/', ' ', $display);
        $joined = (string) preg_replace('/[ \t]*\R[ \t]*/', '', $display);

        Assert::assertTrue(
            str_contains($spaced, $needle) || str_contains($joined, $needle),
            ('' !== $message ? $message."\n" : '').\sprintf('Failed asserting that the console output contains "%s":%s%s', $needle, "\n", $display),
        );
    }
}

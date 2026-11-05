<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Security\AppSecretCheck;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AppSecretCheckTest extends TestCase
{
    public function testAcceptsLongSecret(): void
    {
        AppSecretCheck::assertUsable(str_repeat('a', 32));
        AppSecretCheck::assertUsable(bin2hex(random_bytes(32)));
        AppSecretCheck::assertUsable('ein-sehr-langes-geheimnis-mit-umlauten-äöü-und-mehr');

        $this->expectNotToPerformAssertions();
    }

    public function testRejectsEmptySecret(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('leer');

        AppSecretCheck::assertUsable('');
    }

    public function testRejectsShortSecret(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('zu kurz');

        AppSecretCheck::assertUsable(str_repeat('a', 31));
    }

    #[DataProvider('placeholderProvider')]
    public function testRejectsPlaceholders(string $secret): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Platzhalter');

        AppSecretCheck::assertUsable($secret);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function placeholderProvider(): iterable
    {
        yield 'Vorlage aus docs/examples/env.local.dist' => ['bitte-durch-32-zufaellige-zeichen-ersetzen'];
        yield 'changeme' => ['changeme'];
        yield 'CHANGE_ME' => ['CHANGE_ME'];
        yield 'Symfony-Standard' => ['ThisTokenIsNotSoSecretChangeIt'];
    }
}

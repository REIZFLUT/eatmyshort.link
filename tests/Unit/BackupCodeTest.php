<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\User;
use App\Security\BackupCodeGenerator;
use PHPUnit\Framework\TestCase;

final class BackupCodeTest extends TestCase
{
    public function testGeneratedCodesAreTwelveCharactersInThreeGroups(): void
    {
        $codes = BackupCodeGenerator::generate();

        self::assertCount(10, $codes);
        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/^[a-z2-9]{4}-[a-z2-9]{4}-[a-z2-9]{4}$/', $code);
            self::assertSame(12, \strlen(str_replace('-', '', $code)), '12 characters, not 8 like before');
            self::assertDoesNotMatchRegularExpression('/[ilo01]/', $code, 'no characters that get mixed up');
        }
    }

    public function testReplaceBackupCodesStoresSaltedHashes(): void
    {
        $user = new User();
        $user->replaceBackupCodes(['abcd-efgh-jkmn']);

        $salt = $user->getBackupCodesSalt();
        self::assertNotNull($salt);
        self::assertSame(32, \strlen($salt));
        self::assertSame(1, $user->countBackupCodes());
        self::assertSame([hash('sha256', $salt.'|abcdefghjkmn')], self::hashes($user));
        self::assertNotContains(hash('sha256', 'abcdefghjkmn'), self::hashes($user), 'not the unsalted legacy hash');
    }

    public function testPlainCodeIsAcceptedAndNormalised(): void
    {
        $user = new User();
        $user->replaceBackupCodes(['abcd-efgh-jkmn']);

        self::assertTrue($user->isBackupCode('abcd-efgh-jkmn'));
        self::assertTrue($user->isBackupCode('ABCD EFGH J K M N'));
        self::assertFalse($user->isBackupCode('abcd-efgh-jkmp'));
        self::assertFalse($user->isBackupCode(''));
    }

    public function testInvalidateBackupCodeRemovesOnlyThatCode(): void
    {
        $user = new User();
        $user->replaceBackupCodes(['abcd-efgh-jkmn', 'pqrs-tuvw-xyz2']);

        $user->invalidateBackupCode('abcd-efgh-jkmn');

        self::assertSame(1, $user->countBackupCodes());
        self::assertFalse($user->isBackupCode('abcd-efgh-jkmn'));
        self::assertTrue($user->isBackupCode('pqrs-tuvw-xyz2'));
    }

    public function testNewCodesGetANewSaltAndInvalidateTheOldOnes(): void
    {
        $user = new User();
        $user->replaceBackupCodes(['abcd-efgh-jkmn']);
        $salt = $user->getBackupCodesSalt();

        $user->replaceBackupCodes(['pqrs-tuvw-xyz2']);

        self::assertNotSame($salt, $user->getBackupCodesSalt());
        self::assertFalse($user->isBackupCode('abcd-efgh-jkmn'));
        self::assertTrue($user->isBackupCode('pqrs-tuvw-xyz2'));
    }

    public function testUnsaltedLegacyHashesKeepWorking(): void
    {
        $user = new User();
        self::writeHashes($user, [hash('sha256', 'abcdefgh')]);

        self::assertNull($user->getBackupCodesSalt());
        self::assertTrue($user->isBackupCode('abcd-efgh'));

        $user->invalidateBackupCode('abcd-efgh');
        self::assertSame(0, $user->countBackupCodes());
    }

    /** @return list<string> */
    private static function hashes(User $user): array
    {
        $value = new \ReflectionProperty(User::class, 'backupCodes');
        $raw = $value->getValue($user);
        self::assertIsArray($raw);

        $hashes = [];
        foreach ($raw as $hash) {
            self::assertIsString($hash);
            $hashes[] = $hash;
        }

        return $hashes;
    }

    /** @param list<string> $hashes */
    private static function writeHashes(User $user, array $hashes): void
    {
        new \ReflectionProperty(User::class, 'backupCodes')->setValue($user, $hashes);
    }
}

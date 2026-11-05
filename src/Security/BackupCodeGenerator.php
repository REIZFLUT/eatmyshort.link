<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Human readable one-time backup codes: no i/l/o/0/1, 12 characters (~60 bits)
 * from a 31 symbol alphabet, shown as "xxxx-xxxx-xxxx".
 */
final class BackupCodeGenerator
{
    public const string ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';
    public const int LENGTH = 12;
    private const int GROUP_LENGTH = 4;

    /** @return list<string> */
    public static function generate(int $count = 10): array
    {
        $max = \strlen(self::ALPHABET) - 1;
        $codes = [];
        for ($i = 0; $i < $count; ++$i) {
            $raw = '';
            for ($j = 0; $j < self::LENGTH; ++$j) {
                $raw .= self::ALPHABET[random_int(0, $max)];
            }
            $codes[] = implode('-', str_split($raw, self::GROUP_LENGTH));
        }

        return $codes;
    }
}

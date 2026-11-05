<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Rejects APP_SECRET values that are obviously not secret.
 *
 * TOTP seeds are encrypted with a key derived from APP_SECRET (see SecretBox). A
 * placeholder that ships with the project therefore produces reproducible keys:
 * anyone who knows the placeholder can decrypt the stored seeds.
 */
final class AppSecretCheck
{
    /** Values that appear verbatim in the shipped configuration templates. */
    private const array PLACEHOLDERS = [
        'bitte-durch-32-zufaellige-zeichen-ersetzen',
        'changeme',
        'CHANGE_ME',
        'ThisTokenIsNotSoSecretChangeIt',
    ];

    private const int MIN_LENGTH = 32;

    /**
     * @throws \RuntimeException when the secret is missing, too short or a known placeholder
     */
    public static function assertUsable(string $secret): void
    {
        if ('' === $secret) {
            throw new \RuntimeException('APP_SECRET ist leer. Bitte mindestens 32 zufaellige Zeichen in .env.local eintragen, z. B. mit "php -r \'echo bin2hex(random_bytes(32));\'".');
        }

        if (\in_array($secret, self::PLACEHOLDERS, true)) {
            throw new \RuntimeException('APP_SECRET enthaelt noch einen Platzhalterwert aus der Vorlage. Bitte durch mindestens 32 zufaellige Zeichen ersetzen; aus APP_SECRET werden die TOTP-Schluessel abgeleitet.');
        }

        if (\strlen($secret) < self::MIN_LENGTH) {
            throw new \RuntimeException(\sprintf('APP_SECRET ist zu kurz (%d Zeichen). Bitte mindestens %d zufaellige Zeichen in .env.local eintragen; aus APP_SECRET werden die TOTP-Schluessel abgeleitet.', \strlen($secret), self::MIN_LENGTH));
        }
    }
}

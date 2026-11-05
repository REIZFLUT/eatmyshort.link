<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Five minute window after a successful re-authentication (password or TOTP code)
 * in which security relevant profile actions are allowed.
 */
final class SensitiveConfirmation
{
    public const string SESSION_KEY = '_sensitive_confirmed_at';
    public const int WINDOW_SECONDS = 300;

    public function isConfirmed(?SessionInterface $session): bool
    {
        $confirmedAt = $session?->get(self::SESSION_KEY);

        return \is_int($confirmedAt) && time() - $confirmedAt <= self::WINDOW_SECONDS;
    }

    public function confirm(SessionInterface $session): void
    {
        $session->set(self::SESSION_KEY, time());
    }
}

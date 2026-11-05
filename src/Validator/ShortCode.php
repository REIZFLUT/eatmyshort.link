<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Lowercase letters, digits, "-" and "_", 1-64 characters, not a reserved word.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class ShortCode extends Constraint
{
    public string $formatMessage = 'code.format';
    public string $reservedMessage = 'code.reserved';
}

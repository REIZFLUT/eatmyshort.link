<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class IpOrCidrValidator extends ConstraintValidator
{
    public static function isValid(string $value): bool
    {
        [$ip, $bits] = array_pad(explode('/', trim($value), 2), 2, null);
        if (false === filter_var($ip, \FILTER_VALIDATE_IP)) {
            return false;
        }
        if (null === $bits) {
            return true;
        }
        $max = str_contains((string) $ip, ':') ? 128 : 32;

        return ctype_digit($bits) && (int) $bits >= 0 && (int) $bits <= $max;
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof IpOrCidr) {
            throw new UnexpectedTypeException($constraint, IpOrCidr::class);
        }
        if (null === $value || '' === $value) {
            return;
        }
        if (!self::isValid((string) $value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}

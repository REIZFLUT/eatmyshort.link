<?php

declare(strict_types=1);

namespace App\Validator;

use App\Link\ShortCodeGenerator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ShortCodeValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ShortCode) {
            throw new UnexpectedTypeException($constraint, ShortCode::class);
        }
        if (null === $value || '' === $value) {
            return;
        }

        $code = (string) $value;
        if (1 !== preg_match(ShortCodeGenerator::PATTERN, $code)) {
            $this->context->buildViolation($constraint->formatMessage)->addViolation();

            return;
        }
        if (ShortCodeGenerator::isReserved($code)) {
            $this->context->buildViolation($constraint->reservedMessage)
                ->setParameter('{{ code }}', $code)
                ->addViolation();
        }
    }
}

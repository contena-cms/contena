<?php declare(strict_types=1);

namespace Contena\Core\Framework\Validation\Constraint;

use Contena\Core\Framework\FrameworkException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\Constraint\Uuid as UuidConstraint;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class UuidValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UuidConstraint) {
            throw FrameworkException::unexpectedType($constraint, UuidConstraint::class);
        }

        // custom constraints should ignore null and empty values to allow
        // other constraints (NotBlank, NotNull, etc.) take care of that
        if ($value === null || $value === '') {
            return;
        }

        if (!\is_string($value)) {
            $this->context->buildViolation(UuidConstraint::INVALID_TYPE_MESSAGE)
                ->addViolation();

            return;
        }

        if (!Uuid::isValid($value)) {
            $this->context->buildViolation(UuidConstraint::INVALID_MESSAGE)
                ->setParameter('{{ string }}', $value)
                ->addViolation();
        }
    }
}

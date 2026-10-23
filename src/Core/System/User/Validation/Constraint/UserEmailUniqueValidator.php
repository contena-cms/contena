<?php declare(strict_types=1);

namespace Contena\Core\System\User\Validation\Constraint;

use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Validation\UserEmailUniqueCheck;
use Contena\Core\System\User\Validation\UserEmailUniqueChecker;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class UserEmailUniqueValidator extends ConstraintValidator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly UserEmailUniqueChecker $userEmailUniqueChecker,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UserEmailUnique) {
            throw UserException::unexpectedType($constraint, UserEmailUnique::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if ($this->userEmailUniqueChecker->isUnique(new UserEmailUniqueCheck(
            email: (string) $value,
            boundChannelId: $constraint->getChannelContext()->getChannelId(),
        ))) {
            return;
        }

        $this->context->buildViolation($constraint->getMessage())
            ->setParameter('{{ email }}', $this->formatValue($value))
            ->setCode(UserEmailUnique::CUSTOMER_EMAIL_NOT_UNIQUE)
            ->addViolation();
    }
}

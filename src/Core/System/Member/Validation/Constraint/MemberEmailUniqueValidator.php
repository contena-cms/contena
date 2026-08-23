<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Validation\Constraint;

use Contena\Core\System\Member\MemberException;
use Contena\Core\System\Member\Validation\MemberEmailUniqueCheck;
use Contena\Core\System\Member\Validation\MemberEmailUniqueChecker;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class MemberEmailUniqueValidator extends ConstraintValidator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly MemberEmailUniqueChecker $memberEmailUniqueChecker,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof MemberEmailUnique) {
            throw MemberException::unexpectedType($constraint, MemberEmailUnique::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if ($this->memberEmailUniqueChecker->isUnique(new MemberEmailUniqueCheck(
            email: (string) $value,
            channelId: $constraint->getChannelContext()->getChannelId(),
        ))) {
            return;
        }

        $this->context->buildViolation($constraint->getMessage())
            ->setParameter('{{ email }}', $this->formatValue($value))
            ->setCode(MemberEmailUnique::MEMBER_EMAIL_NOT_UNIQUE)
            ->addViolation();
    }
}

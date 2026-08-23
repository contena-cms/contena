<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Validation\Constraint;

use Contena\Core\System\Member\Channel\AccountService;
use Contena\Core\System\Member\Exception\BadCredentialsException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class MemberPasswordMatchesValidator extends ConstraintValidator
{
    /**
     * @internal
     */
    public function __construct(private readonly AccountService $accountService)
    {
    }

    public function validate(mixed $password, Constraint $constraint): void
    {
        if (!$constraint instanceof MemberPasswordMatches) {
            return;
        }

        $context = $constraint->getChannelContext();

        $member = $context->getMember();

        if (!$member) {
            $this->context->buildViolation($constraint->getMessage())
                ->setCode(MemberPasswordMatches::MEMBER_PASSWORD_NOT_CORRECT)
                ->addViolation();

            return;
        }

        try {
            $this->accountService->getMemberByLogin(
                $member->getEmail(),
                (string) $password,
                $context
            );

            return;
        } catch (BadCredentialsException) {
            $this->context->buildViolation($constraint->getMessage())
                ->setCode(MemberPasswordMatches::MEMBER_PASSWORD_NOT_CORRECT)
                ->addViolation();
        }
    }
}

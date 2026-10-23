<?php declare(strict_types=1);

namespace Contena\Core\System\User\Validation\Constraint;

use Contena\Core\System\User\Exception\BadCredentialsException;
use Contena\Core\System\User\Channel\AccountService;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

class UserPasswordMatchesValidator extends ConstraintValidator
{
    /**
     * @internal
     */
    public function __construct(private readonly AccountService $accountService)
    {
    }

    public function validate(mixed $password, Constraint $constraint): void
    {
        if (!$constraint instanceof UserPasswordMatches) {
            return;
        }

        $context = $constraint->getChannelContext();

        $user = $context->getUser();

        if (!$user) {
            $this->context->buildViolation($constraint->getMessage())
                ->setCode(UserPasswordMatches::CUSTOMER_PASSWORD_NOT_CORRECT)
                ->addViolation();

            return;
        }

        try {
            $this->accountService->getUserByLogin(
                $user->getEmail(),
                (string) $password,
                $context
            );

            return;
        } catch (BadCredentialsException) {
            $this->context->buildViolation($constraint->getMessage())
                ->setCode(UserPasswordMatches::CUSTOMER_PASSWORD_NOT_CORRECT)
                ->addViolation();
        }
    }
}

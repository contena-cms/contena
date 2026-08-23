<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Validation;

use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class PasswordValidationFactory implements DataValidationFactoryInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
    ) {
    }

    public function create(ChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('password.create');

        $this->addConstraints($definition, $context);

        return $definition;
    }

    public function update(ChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('password.update');

        $this->addConstraints($definition, $context);

        return $definition;
    }

    private function addConstraints(DataValidationDefinition $definition, ChannelContext $context): void
    {
        $minLength = $this->systemConfigService->getInt('core.loginRegistration.passwordMinLength', $context->getChannelId());
        if ($minLength < 0) {
            $minLength = null;
        }
        $definition->add('password', new NotBlank(), new Length(min: $minLength, max: PasswordHasherInterface::MAX_PASSWORD_LENGTH, minMessage: 'VIOLATION::PASSWORD_IS_TOO_SHORT', maxMessage: 'VIOLATION::PASSWORD_IS_TOO_LONG'));
    }
}

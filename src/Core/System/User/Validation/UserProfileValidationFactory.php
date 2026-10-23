<?php declare(strict_types=1);

namespace Contena\Core\System\User\Validation;

use Contena\Core\System\User\UserDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\Constraint\NoHtml;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserProfileValidationFactory implements DataValidationFactoryInterface
{
    /**
     * @param string[] $accountTypes
     *
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly array $accountTypes
    ) {
    }

    public function create(ChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('user.profile.create');

        $this->addConstraints($definition, $context);

        return $definition;
    }

    public function update(ChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('user.profile.update');

        $this->addConstraints($definition, $context);

        return $definition;
    }

    private function addConstraints(DataValidationDefinition $definition, ChannelContext $context): void
    {
        $definition
            ->add('salutationId', new EntityExists(entity: SalutationDefinition::ENTITY_NAME, context: $context->getContext()))
            ->add('title', new Length(max: UserDefinition::MAX_LENGTH_TITLE))
            ->add('firstName', new NotBlank(), new Length(max: UserDefinition::MAX_LENGTH_FIRST_NAME))
            ->add('lastName', new NotBlank(), new Length(max: UserDefinition::MAX_LENGTH_LAST_NAME))
            ->add('accountType', new Choice(choices: $this->accountTypes))
            ->add('title', new NoHtml(message: 'VIOLATION::CONTAINS_HTML_ERROR'))
            ->add('firstName', new NoHtml(message: 'VIOLATION::CONTAINS_HTML_ERROR'))
            ->add('lastName', new NoHtml(message: 'VIOLATION::CONTAINS_HTML_ERROR'));

        $channelId = $context->getChannelId();

        if ($this->systemConfigService->get('core.loginRegistration.showBirthdayField', $channelId)
            && $this->systemConfigService->get('core.loginRegistration.birthdayFieldRequired', $channelId)) {
            $definition
                ->add('birthdayDay', new NotBlank(), new GreaterThanOrEqual(value: 1), new LessThanOrEqual(value: 31))
                ->add('birthdayMonth', new NotBlank(), new GreaterThanOrEqual(value: 1), new LessThanOrEqual(value: 12))
                ->add('birthdayYear', new NotBlank(), new GreaterThanOrEqual(value: 1900), new LessThanOrEqual(value: date('Y')));
        }
    }
}

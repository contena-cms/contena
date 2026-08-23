<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Validation;

use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\MemberDefinition;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;

class MemberProfileValidationFactory implements DataValidationFactoryInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    public function create(ChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('member.profile.create');

        $this->addConstraints($definition, $context);

        return $definition;
    }

    public function update(ChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('member.profile.update');

        $this->addConstraints($definition, $context);

        return $definition;
    }

    private function addConstraints(DataValidationDefinition $definition, ChannelContext $context): void
    {
        $definition
            ->add('title', new Length(max: MemberDefinition::MAX_LENGTH_TITLE))
            ->add('name', new NotBlank(), new Length(max: MemberDefinition::MAX_LENGTH_NAME))
            ->add('phoneNumber', new Length(max: MemberDefinition::MAX_LENGTH_PHONE_NUMBER));

        $channelId = $context->getChannelId();

        if ($this->systemConfigService->get('core.loginRegistration.showBirthdayField', $channelId)
            && $this->systemConfigService->get('core.loginRegistration.birthdayFieldRequired', $channelId)) {
            $definition
                ->add('birthdayDay', new GreaterThanOrEqual(value: 1), new LessThanOrEqual(value: 31))
                ->add('birthdayMonth', new GreaterThanOrEqual(value: 1), new LessThanOrEqual(value: 12))
                ->add('birthdayYear', new GreaterThanOrEqual(value: 1900), new LessThanOrEqual(value: date('Y')));
        }
    }
}

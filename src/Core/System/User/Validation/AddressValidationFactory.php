<?php declare(strict_types=1);

namespace Contena\Core\System\User\Validation;

use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Contena\Core\Framework\Validation\Constraint\NoHtml;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressDefinition;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class AddressValidationFactory implements DataValidationFactoryInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly SystemConfigService $systemConfigService)
    {
    }

    public function create(ChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('address.create');

        $this->buildCommonValidation($definition, $context);

        return $definition;
    }

    public function update(ChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('address.update');

        $this->buildCommonValidation($definition, $context)
            ->add('id', new NotBlank(), new EntityExists(entity: 'user_address', context: $context->getContext()));

        return $definition;
    }

    private function buildCommonValidation(DataValidationDefinition $definition, ChannelContext $context): DataValidationDefinition
    {
        $frameworkContext = $context->getContext();
        $channelId = $context->getChannelId();

        $definition
            ->add('countryId', new EntityExists(entity: 'country', context: $frameworkContext))
            ->add('countryStateId', new EntityExists(entity: 'country_state', context: $frameworkContext))
            ->add('name', new NotBlank(message: 'VIOLATION::NAME_IS_BLANK_ERROR', normalizer: $this->trimStringValue(...)))
            ->add('street', new NotBlank(message: 'VIOLATION::STREET_IS_BLANK_ERROR', normalizer: $this->trimStringValue(...)))
            ->add('city', new NotBlank(message: 'VIOLATION::CITY_IS_BLANK_ERROR', normalizer: $this->trimStringValue(...)))
            ->add('countryId', new NotBlank(message: 'VIOLATION::COUNTRY_IS_BLANK_ERROR'), new EntityExists(entity: 'country', context: $frameworkContext))
            ->add('name', new Length(max: UserAddressDefinition::MAX_LENGTH_NAME, exactMessage: 'VIOLATION::NAME_IS_TOO_LONG'))
            ->add('title', new Length(max: UserAddressDefinition::MAX_LENGTH_TITLE, exactMessage: 'VIOLATION::TITLE_IS_TOO_LONG'))
            ->add('zipcode', new Length(max: UserAddressDefinition::MAX_LENGTH_ZIPCODE, exactMessage: 'VIOLATION::ZIPCODE_IS_TOO_LONG'))
            ->add('name', new NoHtml(message: 'VIOLATION::CONTAINS_HTML_ERROR'))
            ->add('title', new NoHtml(message: 'VIOLATION::CONTAINS_HTML_ERROR'))
            ->add('company', new NoHtml(message: 'VIOLATION::CONTAINS_HTML_ERROR'))
            ->add('department', new NoHtml(message: 'VIOLATION::CONTAINS_HTML_ERROR'))
            ->add('street', new NoHtml(message: 'VIOLATION::CONTAINS_HTML_ERROR'))
            ->add('additionalAddressLine1', new NoHtml(message: 'VIOLATION::CONTAINS_HTML_ERROR'))
            ->add('additionalAddressLine2', new NoHtml(message: 'VIOLATION::CONTAINS_HTML_ERROR'))
            ->add('city', new NoHtml(message: 'VIOLATION::CONTAINS_HTML_ERROR'));

        if ($this->systemConfigService->get('core.loginRegistration.showAdditionalAddressField1', $channelId)
            && $this->systemConfigService->get('core.loginRegistration.additionalAddressField1Required', $channelId)) {
            $definition->add('additionalAddressLine1', new NotBlank(message: 'VIOLATION::ADDITIONAL_ADDR1_IS_BLANK_ERROR'));
        }

        if ($this->systemConfigService->get('core.loginRegistration.showAdditionalAddressField2', $channelId)
            && $this->systemConfigService->get('core.loginRegistration.additionalAddressField2Required', $channelId)) {
            $definition->add('additionalAddressLine2', new NotBlank(message: 'VIOLATION::ADDITIONAL_ADDR2_IS_BLANK_ERROR'));
        }

        if ($this->systemConfigService->get('core.loginRegistration.showPhoneNumberField', $channelId)
            && $this->systemConfigService->get('core.loginRegistration.phoneNumberFieldRequired', $channelId)) {
            $definition->add('phoneNumber', new NotBlank(message: 'VIOLATION::PHONE_NUMBER_IS_BLANK_ERROR'));
        }

        if ($this->systemConfigService->get('core.loginRegistration.showPhoneNumberField', $channelId)) {
            $definition->add('phoneNumber', new Length(max: UserAddressDefinition::MAX_LENGTH_PHONE_NUMBER, exactMessage: 'VIOLATION::PHONE_NUMBER_IS_TOO_LONG'));
        }

        return $definition;
    }

    private function trimStringValue(mixed $value): mixed
    {
        if (!\is_string($value)) {
            return $value;
        }

        return trim($value);
    }
}

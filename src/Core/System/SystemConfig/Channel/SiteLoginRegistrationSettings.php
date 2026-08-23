<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Channel;

use Contena\Core\Framework\Struct\Struct;

/**
 * Login, registration and address form settings (core.loginRegistration).
 *
 * @codeCoverageIgnore
 */
final class SiteLoginRegistrationSettings extends Struct
{
    use ConfigCastTrait;

    /**
     * @internal
     */
    public function __construct(
        public readonly int $passwordMinLength,
        public readonly bool $showTitleField,
        public readonly bool $requireEmailConfirmation,
        public readonly bool $requirePasswordConfirmation,
        public readonly bool $doubleOptInRegistration,
        public readonly bool $showPhoneNumberField,
        public readonly bool $phoneNumberFieldRequired,
        public readonly bool $showBirthdayField,
        public readonly bool $birthdayFieldRequired,
        public readonly bool $showAdditionalAddressField1,
        public readonly bool $additionalAddressField1Required,
        public readonly bool $showAdditionalAddressField2,
        public readonly bool $additionalAddressField2Required,
        public readonly string $addressInputFieldArrangement,
        public readonly bool $allowMemberDeletion,
        public readonly bool $requireDataProtectionCheckbox,
    ) {
    }

    /**
     * @internal
     *
     * @param array<string, mixed> $config The values of the core.loginRegistration config domain
     */
    public static function fromConfig(array $config): self
    {
        return new self(
            passwordMinLength: self::intValue($config, 'passwordMinLength'),
            showTitleField: self::boolValue($config, 'showTitleField'),
            requireEmailConfirmation: self::boolValue($config, 'requireEmailConfirmation'),
            requirePasswordConfirmation: self::boolValue($config, 'requirePasswordConfirmation'),
            doubleOptInRegistration: self::boolValue($config, 'doubleOptInRegistration'),
            showPhoneNumberField: self::boolValue($config, 'showPhoneNumberField'),
            phoneNumberFieldRequired: self::boolValue($config, 'phoneNumberFieldRequired'),
            showBirthdayField: self::boolValue($config, 'showBirthdayField'),
            birthdayFieldRequired: self::boolValue($config, 'birthdayFieldRequired'),
            showAdditionalAddressField1: self::boolValue($config, 'showAdditionalAddressField1'),
            additionalAddressField1Required: self::boolValue($config, 'additionalAddressField1Required'),
            showAdditionalAddressField2: self::boolValue($config, 'showAdditionalAddressField2'),
            additionalAddressField2Required: self::boolValue($config, 'additionalAddressField2Required'),
            addressInputFieldArrangement: self::stringValue($config, 'addressInputFieldArrangement'),
            allowMemberDeletion: self::boolValue($config, 'allowMemberDeletion'),
            requireDataProtectionCheckbox: self::boolValue($config, 'requireDataProtectionCheckbox'),
        );
    }

    public function getApiAlias(): string
    {
        return 'site_settings_login_registration';
    }
}

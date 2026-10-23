<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Channel;

use Contena\Core\Framework\Struct\Struct;
use Contena\Core\System\SystemConfig\Channel\ConfigCastTrait;

/**
 * Login, registration and address form settings (core.loginRegistration).
 *
 * @codeCoverageIgnore
 */
final class ShopLoginRegistrationSettings extends Struct
{
    use ConfigCastTrait;

    /**
     * @internal
     */
    public function __construct(
        public readonly int $passwordMinLength,
        public readonly bool $createUserAccountDefault,
        public readonly bool $showSalutation,
        public readonly bool $showTitleField,
        public readonly bool $requireEmailConfirmation,
        public readonly bool $requirePasswordConfirmation,
        public readonly bool $doubleOptInRegistration,
        public readonly bool $doubleOptInGuestOrder,
        public readonly bool $showPhoneNumberField,
        public readonly bool $phoneNumberFieldRequired,
        public readonly bool $showBirthdayField,
        public readonly bool $birthdayFieldRequired,
        public readonly bool $showAccountTypeSelection,
        public readonly bool $showAdditionalAddressField1,
        public readonly bool $additionalAddressField1Required,
        public readonly bool $showAdditionalAddressField2,
        public readonly bool $additionalAddressField2Required,
        public readonly string $addressInputFieldArrangement,
        public readonly bool $allowUserDeletion,
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
            createUserAccountDefault: self::boolValue($config, 'createUserAccountDefault'),
            showSalutation: self::boolValue($config, 'showSalutation'),
            showTitleField: self::boolValue($config, 'showTitleField'),
            requireEmailConfirmation: self::boolValue($config, 'requireEmailConfirmation'),
            requirePasswordConfirmation: self::boolValue($config, 'requirePasswordConfirmation'),
            doubleOptInRegistration: self::boolValue($config, 'doubleOptInRegistration'),
            doubleOptInGuestOrder: self::boolValue($config, 'doubleOptInGuestOrder'),
            showPhoneNumberField: self::boolValue($config, 'showPhoneNumberField'),
            phoneNumberFieldRequired: self::boolValue($config, 'phoneNumberFieldRequired'),
            showBirthdayField: self::boolValue($config, 'showBirthdayField'),
            birthdayFieldRequired: self::boolValue($config, 'birthdayFieldRequired'),
            showAccountTypeSelection: self::boolValue($config, 'showAccountTypeSelection'),
            showAdditionalAddressField1: self::boolValue($config, 'showAdditionalAddressField1'),
            additionalAddressField1Required: self::boolValue($config, 'additionalAddressField1Required'),
            showAdditionalAddressField2: self::boolValue($config, 'showAdditionalAddressField2'),
            additionalAddressField2Required: self::boolValue($config, 'additionalAddressField2Required'),
            addressInputFieldArrangement: self::stringValue($config, 'addressInputFieldArrangement'),
            allowUserDeletion: self::boolValue($config, 'allowUserDeletion'),
            requireDataProtectionCheckbox: self::boolValue($config, 'requireDataProtectionCheckbox'),
        );
    }

    public function getApiAlias(): string
    {
        return 'shop_settings_login_registration';
    }
}

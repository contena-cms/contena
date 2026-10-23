<?php declare(strict_types=1);

namespace Contena\Core\System\User\Validation\Constraint;

use Contena\Core\Framework\Deprecation\BCChange\ParameterRemoval;
use Contena\Core\Framework\Deprecation\BCChange\VisibilityChange;
use Contena\Core\Framework\Feature;
use Contena\Core\System\User\UserException;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserZipCode extends Constraint
{
    final public const ZIP_CODE_INVALID = 'ZIP_CODE_INVALID';

    protected const ERROR_NAMES = [
        NotBlank::IS_BLANK_ERROR => 'IS_BLANK_ERROR',
        self::ZIP_CODE_INVALID => 'ZIP_CODE_INVALID',
    ];

    /**
     * @param ?array{countryId?: ?string, caseSensitiveCheck?: bool} $options
     */
    #[HasNamedArguments]
    #[ParameterRemoval(version: 'v6.8.0', parameterName: 'options', description: 'Use the named arguments instead.')]
    public function __construct(
        $options = null,
        #[VisibilityChange(version: 'v6.8.0', newVisibility: 'protected', description: 'Use isCaseSensitiveCheck() instead.')]
        public bool $caseSensitiveCheck = true,
        #[VisibilityChange(version: 'v6.8.0', newVisibility: 'protected', description: 'Use getCountryId() instead.')]
        public ?string $countryId = null,
        private string $message = 'This value is not a valid ZIP code for country {{ iso }}',
        private string $messageRequired = 'Postal code is required for that country'
    ) {
        if ($options !== null) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', '$options argument is deprecated and will be removed')
            );
        }

        if ($options === null || Feature::isActive('v6.8.0.0')) {
            parent::__construct();
        } else {
            if (!\is_array($options)) {
                $options = [
                    'countryId' => $options,
                ];
            }

            if (\array_key_exists('countryId', $options) && ($options['countryId'] !== null && !\is_string($options['countryId']))) {
                throw UserException::missingOption('countryId', self::class);
            }

            if (isset($options['caseSensitiveCheck']) && !\is_bool($options['caseSensitiveCheck'])) {
                throw UserException::invalidOption('caseSensitiveCheck', 'bool', self::class);
            }

            parent::__construct($options);
        }
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getMessageRequired(): string
    {
        return $this->messageRequired;
    }

    public function getCountryId(): ?string
    {
        return $this->countryId;
    }

    public function isCaseSensitiveCheck(): bool
    {
        return $this->caseSensitiveCheck;
    }
}

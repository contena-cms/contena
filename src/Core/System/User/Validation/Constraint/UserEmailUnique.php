<?php declare(strict_types=1);

namespace Contena\Core\System\User\Validation\Constraint;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Deprecation\BCChange\ParameterRemoval;
use Contena\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Contena\Core\Framework\Deprecation\BCChange\VisibilityChange;
use Contena\Core\Framework\Feature;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\UserException;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

class UserEmailUnique extends Constraint
{
    final public const CUSTOMER_EMAIL_NOT_UNIQUE = '79d30fe0-febf-421e-ac9b-1bfd5c9007f7';

    final public const CUSTOMER_EMAIL_NOT_UNIQUE_CODE = 'VIOLATION::CUSTOMER_EMAIL_NOT_UNIQUE';

    protected const ERROR_NAMES = [
        self::CUSTOMER_EMAIL_NOT_UNIQUE => 'CUSTOMER_EMAIL_NOT_UNIQUE',
    ];

    #[VisibilityChange(version: 'v6.8.0', newVisibility: 'protected', description: 'Use getMessage() instead.')]
    public string $message = 'The email address {{ email }} is already in use.';

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use $channelContext instead
     */
    protected Context $context;

    protected ChannelContext $channelContext;

    /**
     * @param array{channelContext?: ChannelContext, context?: Context}|null $options
     *
     * @internal
     */
    #[HasNamedArguments]
    #[ParameterRemoval(version: 'v6.8.0', parameterName: 'options', description: 'Use the named arguments instead.')]
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'channelContext', newType: ChannelContext::class, description: 'The parameter loses its null default, becomes required and a promoted property.')]
    public function __construct(?array $options = null, ?ChannelContext $channelContext = null, string $message = 'The email address {{ email }} is already in use.')
    {
        if ($options !== null || $channelContext === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Use $channelContext argument instead of providing it in $options array')
            );
        }

        if ($options === null || Feature::isActive('v6.8.0.0')) {
            parent::__construct();

            if ($channelContext === null) {
                throw UserException::missingOption('channelContext', self::class);
            }

            $this->channelContext = $channelContext;
            $this->message = $message;
        } else {
            if (!($options['channelContext'] ?? null) instanceof ChannelContext) {
                throw UserException::missingOption('channelContext', self::class);
            }

            if (!isset($options['context'])) {
                $options['context'] = $options['channelContext']->getContext();
            }

            if (!$options['context'] instanceof Context) {
                throw UserException::missingOption('context', self::class);
            }

            parent::__construct($options);
        }
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use getChannelContext instead
     */
    public function getContext(): Context
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getChannelContext->getContext()')
        );

        return $this->channelContext->getContext();
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}

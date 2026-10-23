<?php declare(strict_types=1);

namespace Contena\Core\System\User\Validation\Constraint;

use Contena\Core\System\User\UserException;
use Contena\Core\Framework\Deprecation\BCChange\ParameterRemoval;
use Contena\Core\Framework\Deprecation\BCChange\ParameterTypeNarrowing;
use Contena\Core\Framework\Deprecation\BCChange\VisibilityChange;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;

class UserPasswordMatches extends Constraint
{
    final public const CUSTOMER_PASSWORD_NOT_CORRECT = 'fe2faa88-34d9-4c3b-99b3-8158b1ed8dc7';

    protected const ERROR_NAMES = [
        self::CUSTOMER_PASSWORD_NOT_CORRECT => 'CUSTOMER_PASSWORD_NOT_CORRECT',
    ];

    #[VisibilityChange(version: 'v6.8.0', newVisibility: 'protected', description: 'Use getMessage() instead.')]
    public string $message = 'Your password is wrong';

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use $channelContext instead
     */
    protected ChannelContext $context;

    /**
     * @deprecated tag:v6.8.0 - Will be changed to natively typed in constructor injection
     */
    protected ChannelContext $channelContext;

    /**
     * @param array{channelContext?: ChannelContext, context?: ChannelContext}|null $options
     *
     * The `$message` property will be natively typed via constructor property promotion in v6.8.0.
     *
     * @internal
     */
    #[HasNamedArguments]
    #[ParameterRemoval(version: 'v6.8.0', parameterName: 'options', description: 'Use the $channelContext argument instead.')]
    #[ParameterTypeNarrowing(version: 'v6.8.0', parameterName: 'channelContext', newType: ChannelContext::class, description: 'The parameter loses its null default, becomes required and a promoted property.')]
    public function __construct(?array $options = null, ?ChannelContext $channelContext = null, string $message = 'Your password is wrong')
    {
        if ($options !== null || $channelContext === null) {
            Feature::triggerDeprecationOrThrow(
                'v6.8.0.0',
                Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Use $channelContext argument instead of providing it in $options array')
            );
        }

        if ($options === null || Feature::isActive('v6.8.0.0')) {
            if ($channelContext === null) {
                throw UserException::missingOption('channelContext', self::class);
            }

            parent::__construct();

            $this->channelContext = $channelContext;
            $this->message = $message;
        } else {
            if (isset($options['context'])) {
                $options['channelContext'] = $options['context'];
            }

            if (!($options['channelContext'] ?? null) instanceof ChannelContext) {
                throw UserException::missingOption('channelContext', self::class);
            }

            parent::__construct($options);
        }
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed, use getChannelContext instead
     */
    public function getContext(): ChannelContext
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'getChannelContext')
        );

        return $this->channelContext;
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

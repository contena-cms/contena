<?php declare(strict_types=1);

namespace Contena\Core\System\User\Extension;

use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Channel\UserResponse;

/**
 * Wraps `RegisterRoute::register`. A listener on the `.pre` event may inspect the
 * submitted data and abort the registration by throwing, or fully replace the
 * registration by assigning `$result` and calling `stopPropagation()`.
 *
 * @extends Extension<UserResponse>
 */
final class RegisterRouteExtension extends Extension
{
    public const NAME = 'register-route.register';

    /**
     * @internal contena owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The submitted registration data
         */
        public readonly RequestDataBag $data,
        /**
         * @public
         *
         * @description The current sales-channel context
         */
        public readonly ChannelContext $context,
        /**
         * @public
         *
         * @description Whether the submitted frontendUrl is validated against the sales-channel domains
         */
        public readonly bool $validateFrontendUrl,
        /**
         * @public
         *
         * @description Additional validation definitions to merge into the registration validation
         */
        public readonly ?DataValidationDefinition $additionalValidationDefinitions,
    ) {
    }
}

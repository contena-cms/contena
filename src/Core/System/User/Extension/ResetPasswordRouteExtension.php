<?php declare(strict_types=1);

namespace Contena\Core\System\User\Extension;

use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;

/**
 * Wraps `ResetPasswordRoute::resetPassword`. A listener on the `.pre` event may
 * resolve the reset itself (e.g. for an alternative account store), assign a
 * `SuccessResponse` to `$result` and call `stopPropagation()` to short-circuit
 * the core flow.
 *
 * @extends Extension<SuccessResponse>
 */
final class ResetPasswordRouteExtension extends Extension
{
    public const NAME = 'reset-password-route.reset-password';

    /**
     * @internal contena owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The submitted reset data (contains hash and new password)
         */
        public readonly RequestDataBag $data,
        /**
         * @public
         *
         * @description The current sales-channel context
         */
        public readonly ChannelContext $context,
    ) {
    }
}

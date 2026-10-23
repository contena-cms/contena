<?php declare(strict_types=1);

namespace Contena\Core\System\User\Extension;

use Contena\Core\System\User\Channel\UserRecoveryIsExpiredResponse;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;

/**
 * Wraps `UserRecoveryIsExpiredRoute::load`. A listener on the `.pre` event
 * may resolve the expiry check itself (e.g. for an alternative account store),
 * assign a `UserRecoveryIsExpiredResponse` to `$result` and call
 * `stopPropagation()` to short-circuit the core flow.
 *
 * @extends Extension<UserRecoveryIsExpiredResponse>
 */
final class UserRecoveryIsExpiredRouteExtension extends Extension
{
    public const NAME = 'user-recovery-is-expired-route.load';

    /**
     * @internal contena owns the __constructor, but the properties are public API
     */
    public function __construct(
        /**
         * @public
         *
         * @description The submitted data (contains the recovery hash)
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

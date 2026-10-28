<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Extension;

use Contena\Core\Checkout\Customer\SalesChannel\CustomerRecoveryIsExpiredResponse;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * Wraps `CustomerRecoveryIsExpiredRoute::load`. A listener on the `.pre` event
 * may resolve the expiry check itself (e.g. for an alternative account store),
 * assign a `CustomerRecoveryIsExpiredResponse` to `$result` and call
 * `stopPropagation()` to short-circuit the core flow.
 *
 * @extends Extension<CustomerRecoveryIsExpiredResponse>
 */
#[Package('checkout')]
final class CustomerRecoveryIsExpiredRouteExtension extends Extension
{
    public const NAME = 'customer-recovery-is-expired-route.load';

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
        public readonly SalesChannelContext $context,
    ) {
    }
}

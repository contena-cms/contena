<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Extension;

use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Checkout\Customer\SalesChannel\LoadWishlistRouteResponse;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<LoadWishlistRouteResponse>
 */
#[Package('checkout')]
final class LoadWishlistRouteExtension extends Extension
{
    public const NAME = 'load-wishlist-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly Request $request,
        public readonly SalesChannelContext $context,
        public readonly Criteria $criteria,
        public readonly CustomerEntity $customer,
    ) {
    }
}

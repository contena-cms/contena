<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Event;

use Contena\Core\Content\Product\ProductCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\ContenaSalesChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerWishlistProductListingResultEvent extends NestedEvent implements ContenaSalesChannelEvent
{
    final public const EVENT_NAME = 'checkout.customer.wishlist_listing_product_result';

    /**
     * @param EntitySearchResult<ProductCollection> $result
     */
    public function __construct(
        protected Request $request,
        protected EntitySearchResult $result,
        private SalesChannelContext $context
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * @return EntitySearchResult<ProductCollection>
     */
    public function getResult(): EntitySearchResult
    {
        return $this->result;
    }

    /**
     * @param EntitySearchResult<ProductCollection> $result
     */
    public function setResult(EntitySearchResult $result): void
    {
        $this->result = $result;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function setSalesChannelContext(SalesChannelContext $context): void
    {
        $this->context = $context;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }
}

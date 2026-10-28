<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\ContenaSalesChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class CustomerWishlistLoaderCriteriaEvent extends NestedEvent implements ContenaSalesChannelEvent
{
    final public const EVENT_NAME = 'checkout.customer.customer_wishlist_loader_criteria';

    public function __construct(
        private readonly Criteria $criteria,
        private readonly SalesChannelContext $context
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }
}

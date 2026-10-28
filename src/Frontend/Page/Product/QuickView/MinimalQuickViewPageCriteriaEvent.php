<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Product\QuickView;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\ContenaSalesChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class MinimalQuickViewPageCriteriaEvent extends Event implements ContenaSalesChannelEvent
{
    public function __construct(
        protected string $productId,
        protected Criteria $criteria,
        protected SalesChannelContext $context,
    ) {
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }
}

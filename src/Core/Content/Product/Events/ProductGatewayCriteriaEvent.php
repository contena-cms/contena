<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Events;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\ContenaSalesChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductGatewayCriteriaEvent extends NestedEvent implements ContenaSalesChannelEvent
{
    /**
     * @param array<string> $ids
     */
    public function __construct(
        protected array $ids,
        protected Criteria $criteria,
        protected SalesChannelContext $context,
    ) {
    }

    /**
     * @return array<string>
     */
    public function getIds(): array
    {
        return $this->ids;
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

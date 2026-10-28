<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\Events;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\ContenaSalesChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class CmsPageLoaderCriteriaEvent extends NestedEvent implements ContenaSalesChannelEvent
{
    public function __construct(
        protected Request $request,
        protected Criteria $criteria,
        protected SalesChannelContext $salesChannelContext,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}

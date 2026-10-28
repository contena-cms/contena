<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - Will be removed together with `SalesChannelContextRestorer::restoreByOrder()`. Load the order with the associations you need and pass it to `OrderConverter::assembleSalesChannelContext()` instead.
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class SalesChannelContextRestorerOrderCriteriaEvent extends NestedEvent
{
    public function __construct(
        protected Criteria $criteria,
        protected Context $context
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0')
        );
    }

    public function getContext(): Context
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0')
        );

        return $this->context;
    }

    public function getCriteria(): Criteria
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0')
        );

        return $this->criteria;
    }
}

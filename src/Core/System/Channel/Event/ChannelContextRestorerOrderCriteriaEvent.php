<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Feature;

/**
 * @deprecated tag:v6.8.0 - Will be removed together with `ChannelContextRestorer::restoreByOrder()`. Load the order with the associations you need and pass it to `OrderConverter::assembleChannelContext()` instead.
 *
 * @codeCoverageIgnore
 */
class ChannelContextRestorerOrderCriteriaEvent extends NestedEvent
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

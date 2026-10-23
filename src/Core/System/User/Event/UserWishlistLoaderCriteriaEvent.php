<?php declare(strict_types=1);

namespace Contena\Core\System\User\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @codeCoverageIgnore
 */
class UserWishlistLoaderCriteriaEvent extends NestedEvent implements ContenaChannelEvent
{
    final public const EVENT_NAME = 'checkout.user.user_wishlist_loader_criteria';

    public function __construct(
        private readonly Criteria $criteria,
        private readonly ChannelContext $context
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

    public function getChannelContext(): ChannelContext
    {
        return $this->context;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }
}

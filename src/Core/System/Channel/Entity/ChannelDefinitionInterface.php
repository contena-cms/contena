<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Entity;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;

interface ChannelDefinitionInterface
{
    /**
     * Called after the api prepared the criteria for the repository.
     * It is possible to remove associations, filters or sortings, throw exception for invalid access
     * or adding some base conditions to filter only active entities or only entities which are relate to the
     * current sales channel id.
     *
     * @example
     *      $criteria->addFilter(new EqualsFilter('blog.active', true));
     *      $criteria->addFilter(new EqualsFilter('currency.channel.id', $context->getChannelId())
     */
    public function processCriteria(Criteria $criteria, ChannelContext $context): void;
}

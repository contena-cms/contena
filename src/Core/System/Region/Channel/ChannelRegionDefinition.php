<?php declare(strict_types=1);

namespace Contena\Core\System\Region\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInterface;
use Contena\Core\System\Region\RegionDefinition;

class ChannelRegionDefinition extends RegionDefinition implements ChannelDefinitionInterface
{
    public function processCriteria(Criteria $criteria, ChannelContext $context): void
    {
        $criteria->addFilter(
            new EqualsFilter('region.country.channels.id', $context->getChannelId()),
        );
    }
}

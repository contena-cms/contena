<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogMainCategory\Channel;

use Contena\Core\Content\Blog\Aggregate\BlogMainCategory\BlogMainCategoryDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInterface;

class ChannelBlogMainCategoryDefinition extends BlogMainCategoryDefinition implements ChannelDefinitionInterface
{
    public function processCriteria(Criteria $criteria, ChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('channelId', $context->getChannelId()));
    }
}

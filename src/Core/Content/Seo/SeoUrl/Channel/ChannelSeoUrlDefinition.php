<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\SeoUrl\Channel;

use Contena\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInterface;

class ChannelSeoUrlDefinition extends SeoUrlDefinition implements ChannelDefinitionInterface
{
    public function processCriteria(Criteria $criteria, ChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('languageId', $context->getLanguageId()));
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('channelId', $context->getChannelId()),
            new EqualsFilter('channelId', null),
        ]));
        if (!$criteria->hasEqualsFilter('isCanonical') && !$criteria->hasEqualsFilter(self::ENTITY_NAME . '.isCanonical')) {
            $criteria->addFilter(new EqualsFilter('isCanonical', true));
        }
        if (!$criteria->hasEqualsFilter('isDeleted') && !$criteria->hasEqualsFilter(self::ENTITY_NAME . '.isDeleted')) {
            $criteria->addFilter(new EqualsFilter('isDeleted', false));
        }
    }
}

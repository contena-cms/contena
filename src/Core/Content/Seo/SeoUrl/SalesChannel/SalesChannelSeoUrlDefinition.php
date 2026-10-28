<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo\SeoUrl\SalesChannel;

use Contena\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Contena\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
class SalesChannelSeoUrlDefinition extends SeoUrlDefinition implements SalesChannelDefinitionInterface
{
    public function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('languageId', $context->getLanguageId()));
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('salesChannelId', $context->getSalesChannelId()),
            new EqualsFilter('salesChannelId', null),
        ]));
        if (!$criteria->hasEqualsFilter('isCanonical') && !$criteria->hasEqualsFilter(self::ENTITY_NAME . '.isCanonical')) {
            $criteria->addFilter(new EqualsFilter('isCanonical', true));
        }
        if (!$criteria->hasEqualsFilter('isDeleted') && !$criteria->hasEqualsFilter(self::ENTITY_NAME . '.isDeleted')) {
            $criteria->addFilter(new EqualsFilter('isDeleted', false));
        }
    }
}

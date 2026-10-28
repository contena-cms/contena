<?php declare(strict_types=1);

namespace Contena\Core\System\Language\SalesChannel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Contena\Core\System\SalesChannel\SalesChannelContext;

#[Package('fundamentals@discovery')]
class SalesChannelLanguageDefinition extends LanguageDefinition implements SalesChannelDefinitionInterface
{
    public function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('language.salesChannels.id', $context->getSalesChannelId()));
    }
}

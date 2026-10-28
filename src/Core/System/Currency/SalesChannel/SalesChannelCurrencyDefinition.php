<?php declare(strict_types=1);

namespace Contena\Core\System\Currency\SalesChannel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Currency\CurrencyDefinition;
use Contena\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Contena\Core\System\SalesChannel\SalesChannelContext;

#[Package('fundamentals@framework')]
class SalesChannelCurrencyDefinition extends CurrencyDefinition implements SalesChannelDefinitionInterface
{
    public function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('currency.salesChannels.id', $context->getSalesChannelId()));
    }
}

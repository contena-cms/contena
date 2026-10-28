<?php declare(strict_types=1);

namespace Contena\Core\System\Country\SalesChannel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Country\CountryDefinition;
use Contena\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Contena\Core\System\SalesChannel\SalesChannelContext;

#[Package('fundamentals@discovery')]
class SalesChannelCountryDefinition extends CountryDefinition implements SalesChannelDefinitionInterface
{
    public function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('country.salesChannels.id', $context->getSalesChannelId()));
    }
}

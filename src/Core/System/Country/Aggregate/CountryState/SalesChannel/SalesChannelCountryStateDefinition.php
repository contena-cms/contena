<?php declare(strict_types=1);

namespace Contena\Core\System\Country\Aggregate\CountryState\SalesChannel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Contena\Core\System\SalesChannel\Entity\SalesChannelDefinitionInterface;
use Contena\Core\System\SalesChannel\SalesChannelContext;

#[Package('fundamentals@discovery')]
class SalesChannelCountryStateDefinition extends CountryStateDefinition implements SalesChannelDefinitionInterface
{
    public function processCriteria(Criteria $criteria, SalesChannelContext $context): void
    {
        $criteria->addFilter(
            new EqualsFilter('country_state.country.salesChannels.id', $context->getSalesChannelId())
        );
    }
}

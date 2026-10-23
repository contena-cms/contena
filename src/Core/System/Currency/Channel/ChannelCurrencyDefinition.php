<?php declare(strict_types=1);

namespace Contena\Core\System\Currency\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Currency\CurrencyDefinition;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInterface;
use Contena\Core\System\Channel\ChannelContext;

class ChannelCurrencyDefinition extends CurrencyDefinition implements ChannelDefinitionInterface
{
    public function processCriteria(Criteria $criteria, ChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('currency.channels.id', $context->getChannelId()));
    }
}

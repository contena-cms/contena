<?php declare(strict_types=1);

namespace Contena\Core\System\Currency\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInterface;
use Contena\Core\System\Currency\CurrencyDefinition;

class ChannelCurrencyDefinition extends CurrencyDefinition implements ChannelDefinitionInterface
{
    public function processCriteria(Criteria $criteria, ChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('currency.channels.id', $context->getChannelId()));
    }
}

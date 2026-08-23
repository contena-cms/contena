<?php declare(strict_types=1);

namespace Contena\Core\System\Language\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInterface;
use Contena\Core\System\Language\LanguageDefinition;

class ChannelLanguageDefinition extends LanguageDefinition implements ChannelDefinitionInterface
{
    public function processCriteria(Criteria $criteria, ChannelContext $context): void
    {
        $criteria->addFilter(new EqualsFilter('language.channels.id', $context->getChannelId()));
    }
}

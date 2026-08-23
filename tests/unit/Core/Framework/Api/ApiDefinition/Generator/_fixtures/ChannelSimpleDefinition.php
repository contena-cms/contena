<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\ApiDefinition\Generator\_fixtures;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInterface;

/**
 * @internal
 */
class ChannelSimpleDefinition extends SimpleDefinition implements ChannelDefinitionInterface
{
    public function processCriteria(Criteria $criteria, ChannelContext $context): void
    {
    }
}

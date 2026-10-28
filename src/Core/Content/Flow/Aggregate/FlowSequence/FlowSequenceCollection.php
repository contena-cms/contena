<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Aggregate\FlowSequence;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<FlowSequenceEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
class FlowSequenceCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'flow_sequence_collection';
    }

    protected function getExpectedClass(): string
    {
        return FlowSequenceEntity::class;
    }
}

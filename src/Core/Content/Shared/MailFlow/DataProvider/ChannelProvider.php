<?php declare(strict_types=1);

namespace Contena\Core\Content\Shared\MailFlow\DataProvider;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Channel\ChannelEntity;

/**
 * @internal
 *
 * @extends AbstractProvider<ChannelEntity, ChannelCollection>
 */
class ChannelProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return ChannelDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        $criteria = new Criteria([$entityId]);

        $criteria->addAssociations([
            'domains',
            'mailHeaderFooter',
        ]);

        return $criteria;
    }
}

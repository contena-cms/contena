<?php declare(strict_types=1);

namespace Contena\Core\System\Integration;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<IntegrationEntity>
 */
class IntegrationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'integration_collection';
    }

    protected function getExpectedClass(): string
    {
        return IntegrationEntity::class;
    }
}

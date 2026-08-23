<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;

/**
 * @extends EntityCollection<VersionCommitDataEntity>
 */
class VersionCommitDataCollection extends EntityCollection
{
    public function filterByEntity(EntityDefinition $definition): self
    {
        return $this->filter(static fn (VersionCommitDataEntity $change) => $change->getEntityName() === $definition->getEntityName());
    }

    public function filterByEntityPrimary(EntityDefinition $definition, array $primary): self
    {
        return $this->filter(static function (VersionCommitDataEntity $change) use ($definition, $primary) {
            if ($change->getEntityName() !== $definition->getEntityName()) {
                return false;
            }
            $diff = array_intersect($primary, $change->getEntityId());

            return $diff === $primary;
        });
    }

    public function getApiAlias(): string
    {
        return 'dal_version_commit_data_collection';
    }

    protected function getExpectedClass(): string
    {
        return VersionCommitDataEntity::class;
    }
}

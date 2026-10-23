<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Version;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<VersionEntity>
 *
 * @codeCoverageIgnore
 */
class VersionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'dal_version_collection';
    }

    protected function getExpectedClass(): string
    {
        return VersionEntity::class;
    }
}

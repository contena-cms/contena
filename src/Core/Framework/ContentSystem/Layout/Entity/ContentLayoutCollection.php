<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Entity;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @final
 *
 * @extends EntityCollection<ContentLayoutEntity>
 */
#[Package('framework')]
class ContentLayoutCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'content_layout_collection';
    }

    protected function getExpectedClass(): string
    {
        return ContentLayoutEntity::class;
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Entity;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal
 *
 * @final
 *
 * @extends EntityCollection<ContentLayoutEntity>
 */
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

<?php declare(strict_types=1);

namespace Contena\Core\System\Tag;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<TagEntity>
 */
class TagCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'tag_collection';
    }

    protected function getExpectedClass(): string
    {
        return TagEntity::class;
    }
}

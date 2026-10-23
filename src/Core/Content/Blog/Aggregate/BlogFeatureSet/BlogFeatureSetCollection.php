<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogFeatureSet;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<BlogFeatureSetEntity>
 *
 * @codeCoverageIgnore
 */
class BlogFeatureSetCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return BlogFeatureSetEntity::class;
    }
}

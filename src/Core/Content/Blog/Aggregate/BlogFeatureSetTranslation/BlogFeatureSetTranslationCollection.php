<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogFeatureSetTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<BlogFeatureSetTranslationEntity>
 *
 * @codeCoverageIgnore
 */
class BlogFeatureSetTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return BlogFeatureSetTranslationEntity::class;
    }
}

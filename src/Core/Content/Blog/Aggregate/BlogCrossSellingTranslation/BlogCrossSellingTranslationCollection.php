<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogCrossSellingTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<BlogCrossSellingTranslationEntity>
 *
 * @codeCoverageIgnore
 */
class BlogCrossSellingTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_cross_selling_assigned_products_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogCrossSellingTranslationEntity::class;
    }
}

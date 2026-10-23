<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogCrossSelling;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<BlogCrossSellingEntity>
 *
 * @codeCoverageIgnore
 */
class BlogCrossSellingCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_cross_selling_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogCrossSellingEntity::class;
    }
}

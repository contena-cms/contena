<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogReview;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<BlogReviewEntity>
 *
 * @codeCoverageIgnore
 */
class BlogReviewCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_review_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogReviewEntity::class;
    }
}

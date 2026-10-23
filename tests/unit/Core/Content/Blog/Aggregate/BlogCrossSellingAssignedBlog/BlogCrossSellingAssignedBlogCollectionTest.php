<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Aggregate\BlogCrossSellingAssignedBlogs;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSellingAssignedBlogs\BlogCrossSellingAssignedBlogsCollection;

/**
 * @internal
 */
#[CoversClass(BlogCrossSellingAssignedBlogsCollection::class)]
class BlogCrossSellingAssignedBlogsCollectionTest extends TestCase
{
    public function testSortByPositionOrdersAscending(): void
    {
        $collection = new BlogCrossSellingAssignedBlogsCollection([
            $this->assignment('c', 30),
            $this->assignment('a', 10),
            $this->assignment('b', 20),
        ]);

        $collection->sortByPosition();

        static::assertSame(['a', 'b', 'c'], array_keys($collection->getElements()));
    }

    public function testGetBlogIdsAndApiAlias(): void
    {
        $a = $this->assignment('a', 1);
        $a->setBlogId('blog-a');
        $b = $this->assignment('b', 2);
        $b->setBlogId('blog-b');

        $collection = new BlogCrossSellingAssignedBlogsCollection([$a, $b]);

        static::assertSame(['blog-a', 'blog-b'], array_values($collection->getBlogIds()));
        static::assertSame('blog_cross_selling_assigned_products_collection', $collection->getApiAlias());
    }

}

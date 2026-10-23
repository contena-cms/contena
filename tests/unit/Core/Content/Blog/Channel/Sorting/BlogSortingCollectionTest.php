<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Sorting;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingCollection;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingEntity;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(BlogSortingCollection::class)]
class BlogSortingCollectionTest extends TestCase
{
    public function testSortByKeyArray(): void
    {
        $blogSortings = $this->buildBlogSortingCollection();

        $blogSortingKeyArray = [
            'foo' => 1,
            'bar' => 5,
        ];

        arsort($blogSortingKeyArray);

        $blogSortings->sortByKeyArray(array_keys($blogSortingKeyArray));

        static::assertEquals(new BlogSortingCollection([
            (new BlogSortingEntity())->assign([
                '_uniqueIdentifier' => 'bar',
                'id' => 'bar',
                'key' => 'bar',
                'fields' => [
                    ['field' => 'bar', 'priority' => 1, 'order' => 'DESC'],
                    ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
                ],
            ]),
            (new BlogSortingEntity())->assign([
                '_uniqueIdentifier' => 'foo',
                'id' => 'foo',
                'key' => 'foo',
                'fields' => [
                    ['field' => 'foo', 'priority' => 1, 'order' => 'DESC'],
                    ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
                ],
            ]),
        ]), $blogSortings);
    }

    public function testGetByKey(): void
    {
        $blogSortings = $this->buildBlogSortingCollection();

        static::assertEquals((new BlogSortingEntity())->assign([
            '_uniqueIdentifier' => 'foo',
            'id' => 'foo',
            'key' => 'foo',
            'fields' => [
                ['field' => 'foo', 'priority' => 1, 'order' => 'DESC'],
                ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
            ],
        ]), $blogSortings->getByKey('foo'));
        static::assertEquals((new BlogSortingEntity())->assign([
            '_uniqueIdentifier' => 'bar',
            'id' => 'bar',
            'key' => 'bar',
            'fields' => [
                ['field' => 'bar', 'priority' => 1, 'order' => 'DESC'],
                ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
            ],
        ]), $blogSortings->getByKey('bar'));
    }

    public function testRemoveByKey(): void
    {
        $blogSortings = $this->buildBlogSortingCollection();

        $blogSortings->removeByKey('foo');

        static::assertEquals(new BlogSortingCollection([
            (new BlogSortingEntity())->assign([
                '_uniqueIdentifier' => 'bar',
                'id' => 'bar',
                'key' => 'bar',
                'fields' => [
                    ['field' => 'bar', 'priority' => 1, 'order' => 'DESC'],
                    ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
                ],
            ]),
        ]), $blogSortings);

        $blogSortings->removeByKey('bar');

        static::assertEquals(new BlogSortingCollection([]), $blogSortings);
    }

    private function buildBlogSortingCollection(): BlogSortingCollection
    {
        $sortings = [
            (new BlogSortingEntity())->assign([
                '_uniqueIdentifier' => 'foo',
                'key' => 'foo',
                'fields' => [
                    ['field' => 'foo', 'priority' => 1, 'order' => 'DESC'],
                    ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
                ],
            ]),
            (new BlogSortingEntity())->assign([
                '_uniqueIdentifier' => 'bar',
                'key' => 'bar',
                'fields' => [
                    ['field' => 'bar', 'priority' => 1, 'order' => 'DESC'],
                    ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
                ],
            ]),
        ];

        foreach ($sortings as $sorting) {
            $sorting->setId($sorting->getKey());
        }

        return new BlogSortingCollection($sortings);
    }
}

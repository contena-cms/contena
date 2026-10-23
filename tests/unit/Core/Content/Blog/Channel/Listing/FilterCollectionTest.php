<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Listing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\Listing\Filter;
use Contena\Core\Content\Blog\Channel\Listing\FilterCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

/**
 * @internal
 */
#[CoversClass(FilterCollection::class)]
class FilterCollectionTest extends TestCase
{
    public function testSetIgnoresNull(): void
    {
        $collection = new FilterCollection();
        $collection->set('empty', null);

        static::assertCount(0, $collection);
    }

    public function testBlacklistReturnsANewCollectionWithoutTheExcludedKey(): void
    {
        $collection = new FilterCollection();
        $collection->set('manufacturer', $this->createFilter('manufacturer'));
        $collection->set('price', $this->createFilter('price'));

        $filtered = $collection->blacklist('price');

        static::assertNotSame($collection, $filtered);
        static::assertSame(['manufacturer'], $filtered->getKeys());
        static::assertSame(['manufacturer', 'price'], $collection->getKeys());
    }

    private function createFilter(string $name): Filter
    {
        return new Filter($name, true, [], new EqualsFilter('blog.id', null), null);
    }
}

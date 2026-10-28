<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Cms\DataResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\Exception\DuplicateCriteriaKeyException;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CriteriaCollection::class)]
class CriteriaCollectionTest extends TestCase
{
    public function testAddSingleCriteria(): void
    {
        $collection = new CriteriaCollection();
        $collection->add('key1', ProductDefinition::class, new Criteria());

        // test array return
        static::assertCount(1, $collection->all());

        // test iterator
        static::assertCount(1, iterator_to_array($collection));
    }

    public function testAddMultipleCriteriaOfDifferentDefinition(): void
    {
        $collection = new CriteriaCollection();
        $collection->add('key1', ProductDefinition::class, new Criteria());
        $collection->add('key2', MediaDefinition::class, new Criteria());
        $collection->add('key3', CategoryDefinition::class, new Criteria());

        // test array return
        static::assertCount(3, $collection->all());

        // test iterator
        static::assertCount(3, iterator_to_array($collection));
    }

    public function testAddMultipleCriteriaOfSameDefinition(): void
    {
        $collection = new CriteriaCollection();
        $collection->add('key1', ProductDefinition::class, new Criteria());
        $collection->add('key2', ProductDefinition::class, new Criteria());
        $collection->add('key3', ProductDefinition::class, new Criteria());

        // test array return
        static::assertCount(1, $collection->all());

        // test iterator
        static::assertCount(1, iterator_to_array($collection));

        // test indexed by definition
        static::assertCount(3, $collection->all()[ProductDefinition::class]);
    }

    public function testAddDuplicates(): void
    {
        $this->expectExceptionObject(new DuplicateCriteriaKeyException('dup_key'));

        $collection = new CriteriaCollection();
        $collection->add('key1', ProductDefinition::class, new Criteria());
        $collection->add('dup_key', ProductDefinition::class, new Criteria());
        $collection->add('dup_key', ProductDefinition::class, new Criteria());
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal\Common;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(RepositoryIterator::class)]
class RepositoryIteratorTest extends TestCase
{
    public function testFetchUsesAutoIncrementCursorWhenCriteriaHasNoSorting(): void
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria()->setLimit(2)->setOffset(9);
        $blogA = $this->blog(5);
        $blogB = $this->blog(9);
        $calls = 0;

        $search = function (Criteria $searchCriteria) use (&$calls, $context, $blogA, $blogB): EntitySearchResult {
            static::assertSame(Criteria::TOTAL_COUNT_MODE_NONE, $searchCriteria->getTotalCountMode());
            static::assertSame(0, $searchCriteria->getOffset());
            static::assertSame('autoIncrement', $searchCriteria->getSorting()[0]->getField());

            $range = $searchCriteria->getFilters()['increment'] ?? null;
            static::assertInstanceOf(RangeFilter::class, $range);

            if ($calls === 0) {
                static::assertSame(0, $range->getParameter(RangeFilter::GTE));
                $entities = new BlogCollection([$blogA, $blogB]);
            } else {
                static::assertSame(9, $range->getParameter(RangeFilter::GT));
                $entities = new BlogCollection();
            }

            ++$calls;

            return new EntitySearchResult($entities->count(), $entities, null, $searchCriteria, $context);
        };

        $repository = StaticEntityRepository::of(BlogCollection::class, [$search, $search], new BlogDefinition());

        $iterator = new RepositoryIterator($repository, $context, $criteria);

        static::assertNotNull($iterator->fetch());
        static::assertNull($iterator->fetch());
    }

    public function testFetchKeepsOffsetPaginationForCriteriaWithSorting(): void
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria()->setLimit(2)->setOffset(3);
        $criteria->addSorting(new FieldSorting('name'));

        $repository = StaticEntityRepository::of(BlogCollection::class, [
            function (Criteria $searchCriteria) use ($context): EntitySearchResult {
                static::assertSame(3, $searchCriteria->getOffset());
                static::assertArrayNotHasKey('increment', $searchCriteria->getFilters());

                return new EntitySearchResult(1, new BlogCollection([$this->blog(1)]), null, $searchCriteria, $context);
            },
        ], new BlogDefinition());

        $iterator = new RepositoryIterator($repository, $context, $criteria);

        static::assertNotNull($iterator->fetch());
        static::assertSame(5, $criteria->getOffset());
    }

    public function testFetchAddsAutoIncrementCursorToPartialFields(): void
    {
        $criteria = new Criteria()->setLimit(2);
        $criteria->addFields(['name']);

        $repository = StaticEntityRepository::of(BlogCollection::class, [], new BlogDefinition());

        new RepositoryIterator($repository, Context::createDefaultContext(), $criteria);

        static::assertSame(['name', 'autoIncrement'], $criteria->getFields());
    }

    public function testFetchIdsKeepsOffsetPaginationForCriteriaWithSorting(): void
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria()->setLimit(2)->setOffset(3);
        $criteria->addSorting(new FieldSorting('name'));
        $id = Uuid::randomHex();

        $repository = StaticEntityRepository::of(BlogCollection::class, [
            function (Criteria $searchCriteria) use ($id): array {
                static::assertSame(Criteria::TOTAL_COUNT_MODE_NONE, $searchCriteria->getTotalCountMode());
                static::assertSame(3, $searchCriteria->getOffset());
                static::assertArrayNotHasKey('increment', $searchCriteria->getFilters());

                return [$id];
            },
        ], new BlogDefinition());

        $iterator = new RepositoryIterator($repository, $context, $criteria);

        static::assertSame([$id], $iterator->fetchIds());
        static::assertSame(5, $criteria->getOffset());
    }

    private function blog(int $autoIncrement): BlogEntity
    {
        $blog = new BlogEntity();
        $blog->setId(Uuid::randomHex());
        $blog->setAutoIncrement($autoIncrement);

        return $blog;
    }
}

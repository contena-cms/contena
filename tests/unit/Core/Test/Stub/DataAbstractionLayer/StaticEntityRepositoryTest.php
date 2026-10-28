<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Test\Stub\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\ProductCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
class StaticEntityRepositoryTest extends TestCase
{
    #[TestDox('records the criteria of every search and id search in call order')]
    public function testRecordsSearchedCriteriaInCallOrder(): void
    {
        $context = Context::createDefaultContext();
        $repository = StaticEntityRepository::of(ProductCollection::class, [new ProductCollection(), []]);
        $search = (new Criteria())->addFilter(new EqualsFilter('active', true));
        $searchIds = (new Criteria())->setLimit(5);

        $repository->search($search, $context);
        $repository->searchIds($searchIds, $context);

        static::assertEquals([$search, $searchIds], $repository->searchedCriteria);
    }

    #[TestDox('keeps the criteria as they were at call time when the caller changes them afterwards')]
    public function testRecordedCriteriaIgnoreLaterChanges(): void
    {
        $repository = StaticEntityRepository::of(ProductCollection::class, [[]]);
        $criteria = (new Criteria())->setOffset(0);

        $repository->searchIds($criteria, Context::createDefaultContext());
        $criteria->setOffset(50);

        static::assertSame(0, $repository->searchedCriteria[0]->getOffset());
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Struct\ArrayEntity;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(EntitySearchResult::class)]
class EntitySearchResultTest extends TestCase
{
    #[DataProvider('resultPageCriteriaDataProvider')]
    public function testResultPage(Criteria $criteria, int $page): void
    {
        $entity = new ArrayEntity(['id' => Uuid::randomHex()]);
        $entityCollection = new EntityCollection([$entity]);
        $result = new EntitySearchResult(
            100,
            $entityCollection,
            null,
            $criteria,
            Context::createDefaultContext()
        );

        static::assertSame($page, $result->getPage());
    }

    public static function resultPageCriteriaDataProvider(): \Generator
    {
        // Criteria, Page
        yield [new Criteria()->setLimit(5)->setOffset(0), 1];
        yield [new Criteria()->setLimit(5)->setOffset(1), 1];
        yield [new Criteria()->setLimit(5)->setOffset(9), 2];
        yield [new Criteria()->setLimit(5)->setOffset(10), 3];
        yield [new Criteria()->setLimit(5)->setOffset(11), 3];
        yield [new Criteria()->setLimit(10)->setOffset(25), 3];
    }
}

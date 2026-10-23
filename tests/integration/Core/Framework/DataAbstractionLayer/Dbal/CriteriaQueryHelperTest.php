<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Exception\InvalidSortingDirectionException;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Feature;
use Contena\Core\System\Tax\TaxCollection;

/**
 * @internal
 */
class CriteriaQueryHelperTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testInvalidSortingDirection(): void
    {
        $context = Context::createDefaultContext();
        /** @var EntityRepository<TaxCollection> $taxRepository */
        $taxRepository = static::getContainer()->get('tax.repository');

        $criteria = new Criteria();

        $criteria->addSorting(new FieldSorting('rate', 'invalid direction'));

        if (Feature::isActive('v6.8.0.0')) {
            static::expectExceptionObject(DataAbstractionLayerException::invalidSortingDirection('invalid direction'));
        } else {
            static::expectException(InvalidSortingDirectionException::class);
        }
        $taxRepository->search($criteria, $context);
    }

    public function testDoNotSortByScoreAutomaticallyIfNoScoreQueryOrSearchTermIsSet(): void
    {
        $blogDefinition = static::getContainer()->get(BlogDefinition::class);
        $queryMock = $this->createMock(QueryBuilder::class);
        $queryMock
            ->expects($this->never())
            ->method('addOrderBy');

        $builder = static::getContainer()->get(CriteriaQueryBuilder::class);
        $builder->build($queryMock, $blogDefinition, new Criteria(), Context::createDefaultContext());
    }

    public function testDoNotSortByScoreManuallyIfNoScoreQueryOrSearchTermIsSet(): void
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('_score'));
        $blogDefinition = static::getContainer()->get(BlogDefinition::class);
        $queryMock = $this->createMock(QueryBuilder::class);
        $queryMock
            ->expects($this->never())
            ->method('addOrderBy');

        $builder = static::getContainer()->get(CriteriaQueryBuilder::class);
        $builder->build($queryMock, $blogDefinition, $criteria, Context::createDefaultContext());
    }

    public function testSortByScoreIfScoreQueryIsSet(): void
    {
        $blogDefinition = static::getContainer()->get(BlogDefinition::class);
        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('name', 'test matching'), 1000));
        $queryMock = $this->createMock(QueryBuilder::class);
        $queryMock
            ->expects($this->once())
            ->method('addOrderBy')
            ->with('_score', 'DESC');

        $builder = static::getContainer()->get(CriteriaQueryBuilder::class);
        $builder->build($queryMock, $blogDefinition, $criteria, Context::createDefaultContext());
    }

    public function testSortByScoreIfSearchTermIsSet(): void
    {
        $blogDefinition = static::getContainer()->get(BlogDefinition::class);
        $criteria = new Criteria();
        $criteria->setTerm('searchTerm');
        $queryMock = $this->createMock(QueryBuilder::class);
        $queryMock
            ->expects($this->once())
            ->method('addOrderBy')
            ->with('_score', 'DESC');

        $builder = static::getContainer()->get(CriteriaQueryBuilder::class);
        $builder->build($queryMock, $blogDefinition, $criteria, Context::createDefaultContext());
    }

    public function testSortByScoreAndAdditionalSorting(): void
    {
        $blogDefinition = static::getContainer()->get(BlogDefinition::class);
        $criteria = new Criteria();
        $criteria->setTerm('searchTerm');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));

        $queryBuilder = new QueryBuilder(static::createStub(Connection::class));

        $builder = static::getContainer()->get(CriteriaQueryBuilder::class);
        $builder->build($queryBuilder, $blogDefinition, $criteria, Context::createDefaultContext());

        static::assertSame($queryBuilder->getOrderByParts(), [
            'MIN(`blog`.`created_at`) ASC',
            '_score DESC',
        ]);
    }

    public function testSortByScoreAndAdditionalSortingWithScore(): void
    {
        $blogDefinition = static::getContainer()->get(BlogDefinition::class);
        $criteria = new Criteria();
        $criteria->setTerm('searchTerm');
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('_score', FieldSorting::ASCENDING));
        $queryBuilder = new QueryBuilder(static::createStub(Connection::class));

        $builder = static::getContainer()->get(CriteriaQueryBuilder::class);
        $builder->build($queryBuilder, $blogDefinition, $criteria, Context::createDefaultContext());

        static::assertSame($queryBuilder->getOrderByParts(), [
            'MIN(`blog`.`created_at`) ASC',
            '_score ASC',
        ]);
    }
}

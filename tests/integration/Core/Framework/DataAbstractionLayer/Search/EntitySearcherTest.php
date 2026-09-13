<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Search;

use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Contena\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class EntitySearcherTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    private EntitySearcher $entitySearcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->entitySearcher = new EntitySearcher(
            static::getContainer()->get(Connection::class),
            static::getContainer()->get(EntityDefinitionQueryHelper::class),
            static::getContainer()->get(CriteriaQueryBuilder::class),
        );
    }

    public function testNextPagesCountIsBoundedByTheLookaheadWindow(): void
    {
        $ids = new IdsCollection();
        $blogs = [];

        foreach (range(1, 8) as $number) {
            $blogName = 'next-pages-' . $number;
            $blogs[] = new BlogBuilder($ids, $blogName)->build();
        }

        $context = Context::createDefaultContext();
        $this->blogRepository->create($blogs, $context);

        $criteria = new Criteria(array_values($ids->getList(array_map(
            static fn (int $number): string => 'next-pages-' . $number,
            range(1, 8)
        ))));
        $criteria->setLimit(1);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);

        $result = $this->blogRepository->search($criteria, $context);

        static::assertCount(1, $result->getEntities());
        static::assertSame(7, $result->getTotal());
    }

    public function testScoreRankingSupportsCombinedPrimaryKeys(): void
    {
        $ids = new IdsCollection();
        $this->blogRepository->create([
            new BlogBuilder($ids, 'mapped')->category('category')->build(),
        ], Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(new EqualsFilter('categoryId', $ids->get('category')), score: 100));
        $criteria->addGroupField(new FieldGrouping('categoryId'));
        $criteria->addState(Criteria::STATE_SCORE_RANKED_GROUPING);

        $result = $this->entitySearcher->search(
            static::getContainer()->get(BlogCategoryDefinition::class),
            $criteria,
            Context::createDefaultContext(),
        );

        static::assertSame(
            [['blogId' => $ids->get('mapped'), 'categoryId' => $ids->get('category')]],
            $result->getIds()
        );
    }
}

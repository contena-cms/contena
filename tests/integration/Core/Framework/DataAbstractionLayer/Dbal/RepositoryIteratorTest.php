<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\System\SystemConfig\SystemConfigCollection;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class RepositoryIteratorTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testIteratedSearch(): void
    {
        $context = Context::createDefaultContext();
        /** @var EntityRepository<SystemConfigCollection> $systemConfigRepository */
        $systemConfigRepository = static::getContainer()->get('system_config.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('configurationKey', 'core'));
        $criteria->setLimit(1);

        /** @var RepositoryIterator<SystemConfigCollection> $iterator */
        $iterator = new RepositoryIterator($systemConfigRepository, $context, $criteria);

        $offset = 1;
        while (($result = $iterator->fetch()) !== null) {
            static::assertNotEmpty($result->getEntities()->first()?->getId());
            static::assertEquals(
                [new ContainsFilter('configurationKey', 'core')],
                $criteria->getFilters()
            );
            static::assertCount(0, $criteria->getPostFilters());
            static::assertSame($offset, $criteria->getOffset());
            ++$offset;
        }
    }

    public function testFetchIdsIsNotRunningInfinitely(): void
    {
        $context = Context::createDefaultContext();
        /** @var EntityRepository<SystemConfigCollection> $systemConfigRepository */
        $systemConfigRepository = static::getContainer()->get('system_config.repository');

        $iterator = new RepositoryIterator($systemConfigRepository, $context, new Criteria());

        $iteration = 0;
        while ($iterator->fetchIds() !== null && $iteration < 100) {
            ++$iteration;
        }

        static::assertTrue($iteration < 100);
    }

    public function testFetchIdAutoIncrement(): void
    {
        /** @var EntityRepository<BlogCollection> $blogRepository */
        $blogRepository = static::getContainer()->get('blog.repository');

        $context = Context::createDefaultContext();

        $ids = new IdsCollection();

        $blogRepository->create([new BlogBuilder($ids, 'blog1')->build()], $context);
        $blogRepository->create([new BlogBuilder($ids, 'blog2')->build()], $context);
        $blogRepository->create([new BlogBuilder($ids, 'blog3')->build()], $context);

        $criteria = new Criteria([$ids->get('blog1'), $ids->get('blog2'), $ids->get('blog3')]);
        $criteria->setLimit(1);
        $iterator = new RepositoryIterator($blogRepository, $context, $criteria);

        $totalFetchedIds = 0;
        while ($iterator->fetchIds()) {
            ++$totalFetchedIds;
        }
        static::assertSame($totalFetchedIds, 3);
    }

    public function testFetchAutoIncrementDoesNotSkipBatches(): void
    {
        /** @var EntityRepository<BlogCollection> $blogRepository */
        $blogRepository = static::getContainer()->get('blog.repository');

        $context = Context::createDefaultContext();
        $ids = new IdsCollection();

        foreach (['blog1', 'blog2', 'blog3'] as $blogName) {
            $blogRepository->create([new BlogBuilder($ids, $blogName)->build()], $context);
        }

        $criteria = new Criteria(array_values($ids->getList(['blog1', 'blog2', 'blog3'])));
        $criteria->setLimit(1);
        $iterator = new RepositoryIterator($blogRepository, $context, $criteria);

        $fetchedIds = [];
        while (($result = $iterator->fetch()) !== null) {
            $fetchedIds[] = $result->getEntities()->first()?->getId();
        }

        static::assertSame(
            [$ids->get('blog1'), $ids->get('blog2'), $ids->get('blog3')],
            $fetchedIds
        );
    }

    public function testFetchWithSortingUsesOffsetPagination(): void
    {
        /** @var EntityRepository<BlogCollection> $blogRepository */
        $blogRepository = static::getContainer()->get('blog.repository');

        $context = Context::createDefaultContext();
        $ids = new IdsCollection();

        foreach (['blog1', 'blog2', 'blog3'] as $blogName) {
            $blogRepository->create([new BlogBuilder($ids, $blogName)->build()], $context);
        }

        $criteria = new Criteria(array_values($ids->getList(['blog1', 'blog2', 'blog3'])));
        $criteria->addSorting(new FieldSorting('name', FieldSorting::DESCENDING));
        $criteria->setLimit(1);
        $iterator = new RepositoryIterator($blogRepository, $context, $criteria);

        $fetchedIds = [];
        while (($result = $iterator->fetch()) !== null) {
            $fetchedIds[] = $result->getEntities()->first()?->getId();
        }

        static::assertSame(
            [$ids->get('blog3'), $ids->get('blog2'), $ids->get('blog1')],
            $fetchedIds
        );
    }
}

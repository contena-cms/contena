<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Search;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Test\Stub\Framework\IdsCollection;

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

    protected function setUp(): void
    {
        parent::setUp();

        $this->blogRepository = static::getContainer()->get('blog.repository');
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
}

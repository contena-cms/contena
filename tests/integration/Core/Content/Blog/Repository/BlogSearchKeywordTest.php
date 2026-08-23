<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Repository;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class BlogSearchKeywordTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $repository;

    private Context $context;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('blog.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testAddBlogWithSearchKeyword(): void
    {
        $id = Uuid::randomHex();
        $this->createBlog($id, ['YTN', 'Search Keyword']);

        $blog = $this->getBlog($id);
        $customSearchKeywords = $blog->getCustomSearchKeywords();

        static::assertIsArray($customSearchKeywords);
        static::assertContains('YTN', $customSearchKeywords);
        static::assertContains('Search Keyword', $customSearchKeywords);
    }

    public function testEditBlogWithSearchKeyword(): void
    {
        $id = Uuid::randomHex();
        $this->createBlog($id, ['YTN']);

        $blog = $this->getBlog($id);
        $customSearchKeywords = $blog->getCustomSearchKeywords();
        static::assertIsArray($customSearchKeywords);
        static::assertContains('YTN', $customSearchKeywords);

        $this->repository->update([
            ['id' => $id, 'customSearchKeywords' => ['YTN', 'Search Keyword Update']],
        ], $this->context);

        $blog = $this->getBlog($id);
        $customSearchKeywords = $blog->getCustomSearchKeywords();
        static::assertIsArray($customSearchKeywords);
        static::assertContains('YTN', $customSearchKeywords);
        static::assertContains('Search Keyword Update', $customSearchKeywords);
    }

    /**
     * @param list<string> $searchKeywords
     */
    private function createBlog(string $id, array $searchKeywords): void
    {
        $this->repository->create([[
            'id' => $id,
            'name' => 'Test blog',
            'customSearchKeywords' => $searchKeywords,
        ]], $this->context);
    }

    private function getBlog(string $id): BlogEntity
    {
        $blog = $this->repository->search(new Criteria([$id]), $this->context)->getEntities()->get($id);
        static::assertInstanceOf(BlogEntity::class, $blog);

        return $blog;
    }
}

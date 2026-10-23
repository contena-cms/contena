<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Repository;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
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

        /** @var BlogEntity $blog */
        $blog = $this->repository
            ->search(new Criteria([$id]), $this->context)
            ->getEntities()
            ->get($id);

        $customSearchKeywords = $blog->getCustomSearchKeywords();
        static::assertIsArray($customSearchKeywords);
        static::assertContains('YTN', $customSearchKeywords);
        static::assertContains('Search Keyword', $customSearchKeywords);
    }

    public function testEditBlogWithSearchKeyword(): void
    {
        $id = Uuid::randomHex();

        $this->createBlog($id, ['YTN']);

        /** @var BlogEntity $blog */
        $blog = $this->repository
            ->search(new Criteria([$id]), $this->context)
            ->getEntities()
            ->get($id);

        $customSearchKeywords = $blog->getCustomSearchKeywords();
        static::assertIsArray($customSearchKeywords);
        static::assertContains('YTN', $customSearchKeywords);

        $update = [
            'id' => $id,
            'customSearchKeywords' => ['YTN', 'Search Keyword Update'],
        ];

        $this->repository->update([$update], $this->context);

        /** @var BlogEntity $blog */
        $blog = $this->repository
            ->search(new Criteria([$id]), $this->context)
            ->getEntities()
            ->get($id);

        $customSearchKeywords = $blog->getCustomSearchKeywords();
        static::assertIsArray($customSearchKeywords);
        static::assertContains('YTN', $customSearchKeywords);
        static::assertContains('Search Keyword Update', $customSearchKeywords);
    }

    /**
     * @param array<string> $searchKeyword
     */
    private function createBlog(string $id, array $searchKeyword): void
    {
        $data = [
            'id' => $id,
            'name' => 'test',
            'blogNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['id' => '98432def39fc4624b33213a56b8c944f', 'name' => 'test'],
            'tax' => ['id' => '98432def39fc4624b33213a56b8c944f', 'name' => 'test', 'taxRate' => 15],
            'customSearchKeywords' => $searchKeyword,
        ];

        $this->repository->create([$data], $this->context);
    }
}

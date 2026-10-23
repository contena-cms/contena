<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class BlogCategoryDenormalizerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Context $context;

    private Connection $connection;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
        $this->blogRepository = static::getContainer()->get('blog.repository');
    }

    public function testUpdateWithBlogAddedCategoriesWillVariantGetSameCategories(): void
    {
        ['blogs' => $blogFixture, 'categories' => $categoryIds] = $this->prepareData();

        $this->blogRepository->update([
            [
                'id' => $blogFixture['testable-blog'],
                'categories' => \array_map(static fn (string $categoryId) => ['id' => $categoryId], $categoryIds),
            ],
        ], $this->context);

        static::assertSame($categoryIds, $this->getBlogCategoryList($blogFixture['testable-blog']));
        static::assertSame(
            \count($categoryIds),
            $this->getCountRowsInBlogCategoryTree($blogFixture['testable-blog'], $categoryIds)
        );

        static::assertSame($categoryIds, $this->getBlogCategoryList($blogFixture['variant-testable-blog']));
        static::assertSame(
            \count($categoryIds),
            $this->getCountRowsInBlogCategoryTree($blogFixture['variant-testable-blog'], $categoryIds)
        );
    }

    public function testFullIndexIncludesParentCategoriesInTheBlogCategoryTree(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(EntityIndexerRegistry::DISABLE_INDEXING);

        $rootCategoryId = Uuid::randomHex();
        $childCategoryId = Uuid::randomHex();
        static::getContainer()->get('category.repository')->create([
            ['id' => $rootCategoryId, 'name' => 'Root'],
            ['id' => $childCategoryId, 'parentId' => $rootCategoryId, 'name' => 'Child'],
        ], $context);

        $blogId = Uuid::randomHex();
        $this->blogRepository->create([
            [
                'id' => $blogId,
                'blogNumber' => $blogId,
                'stock' => 1,
                'name' => 'Blog',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 10, 'linked' => false]],
                'tax' => ['name' => 'Tax', 'taxRate' => 19],
                'categories' => [['id' => $childCategoryId]],
            ],
        ], $context);

        static::getContainer()->get(EntityIndexerRegistry::class)->index(false);

        static::assertSame(1, $this->getCountRowsInBlogCategoryTree($blogId, [$rootCategoryId]));
    }

    /**
     * @return array<string>|null
     */
    private function getBlogCategoryList(string $blogId): ?array
    {
        $blogRepository = static::getContainer()->get('blog.repository');
        /** @var BlogEntity $testableBlog */
        $testableBlog = $blogRepository->search(new Criteria([$blogId]), $this->context)->getEntities()->first();

        $blogCategoryIds = $testableBlog->getCategoryTree();
        if ($blogCategoryIds !== null) {
            \sort($blogCategoryIds);
        }

        return $blogCategoryIds;
    }

    /**
     * @param list<string> $categoryIds
     */
    private function getCountRowsInBlogCategoryTree(string $blogId, array $categoryIds): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) as cnt FROM blog_category_tree WHERE blog_id = :blogId AND category_id IN (:categoryIds)',
            [
                'blogId' => Uuid::fromHexToBytes($blogId),
                'categoryIds' => Uuid::fromHexToBytesList($categoryIds),
            ],
            ['categoryIds' => ArrayParameterType::BINARY]
        );
    }

    /**
     * @return array{blogs: array{blog-with-category: string, testable-blog: string, variant-testable-blog: string}, categories: list<string>}
     */
    private function prepareData(): array
    {
        $ids = new IdsCollection();

        $blogs = [];

        $name = 'blog-with-category';
        $builder = new BlogBuilder($ids, $name);
        $builder->price(200)
            ->categories(['cat1', 'cat2'])
            ->write(static::getContainer());
        /** @var array{id: string, children: array<int, array{id: string}>, categories: array<int, array{id: string, name:string}>} $blog */
        $blog = $builder->build();
        $blogs[$name] = $blog['id'];
        $categories = \array_column($blog['categories'], 'id');
        \sort($categories);

        $name = 'testable-blog';
        $builder = new BlogBuilder($ids, $name);
        $builder->price(100)
            ->variant(
                (new BlogBuilder($ids, 'variant-testable-blog'))
                ->price(100)->build()
            )
            ->write(static::getContainer());

        $blog = $builder->build();
        $blogs[$name] = $blog['id'];
        $blogs['variant-testable-blog'] = $blog['children'][0]['id'];

        static::assertSame(
            0,
            $this->getCountRowsInBlogCategoryTree($blogs['variant-testable-blog'], $categories)
        );

        return [
            'blogs' => $blogs,
            'categories' => $categories,
        ];
    }
}

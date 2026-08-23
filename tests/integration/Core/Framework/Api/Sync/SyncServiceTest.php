<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Sync;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Framework\Api\Sync\SyncBehavior;
use Contena\Core\Framework\Api\Sync\SyncOperation;
use Contena\Core\Framework\Api\Sync\SyncService;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class SyncServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private SyncService $service;

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = static::getContainer()->get(SyncService::class);
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testDeleteViaCriteria(): void
    {
        $categoryId = Uuid::randomHex();
        $firstBlogId = Uuid::randomHex();
        $secondBlogId = Uuid::randomHex();

        /** @var EntityRepository<CategoryCollection> $categoryRepository */
        $categoryRepository = static::getContainer()->get('category.repository');
        $categoryRepository->create([['id' => $categoryId, 'name' => 'category']], Context::createDefaultContext());

        /** @var EntityRepository<BlogCollection> $blogRepository */
        $blogRepository = static::getContainer()->get('blog.repository');
        $blogRepository->create([
            ['id' => $firstBlogId, 'name' => 'first', 'categories' => [['id' => $categoryId]]],
            ['id' => $secondBlogId, 'name' => 'second'],
        ], Context::createDefaultContext());

        $this->service->sync([
            new SyncOperation(
                'delete-blogs',
                'blog',
                SyncOperation::ACTION_DELETE,
                [],
                [['type' => 'equals', 'field' => 'categories.id', 'value' => $categoryId]]
            ),
        ], Context::createDefaultContext(), new SyncBehavior());

        $existing = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM blog WHERE id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList([$firstBlogId, $secondBlogId])],
            ['ids' => ArrayParameterType::BINARY]
        );

        static::assertSame([$secondBlogId], $existing);
    }

    public function testSendNoneExistingId(): void
    {
        $id = Uuid::randomHex();
        $result = $this->service->sync([
            new SyncOperation('delete-blog', 'blog', SyncOperation::ACTION_DELETE, [['id' => $id]]),
        ], Context::createDefaultContext(), new SyncBehavior());

        static::assertSame([], $result->getDeleted());
        static::assertSame(['blog' => [$id]], $result->getNotFound());
    }

    public function testDeleteWithWildCards(): void
    {
        $ids = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];
        $categoryIds = [Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex(), Uuid::randomHex()];

        /** @var EntityRepository<CategoryCollection> $categoryRepository */
        $categoryRepository = static::getContainer()->get('category.repository');
        $categoryRepository->create(array_map(static fn (string $id): array => ['id' => $id, 'name' => $id], $categoryIds), Context::createDefaultContext());

        /** @var EntityRepository<BlogCollection> $blogRepository */
        $blogRepository = static::getContainer()->get('blog.repository');
        $blogRepository->create([
            ['id' => $ids[0], 'name' => 'one', 'categories' => [['id' => $categoryIds[0]], ['id' => $categoryIds[1]]]],
            ['id' => $ids[1], 'name' => 'two', 'categories' => [['id' => $categoryIds[0]], ['id' => $categoryIds[2]]]],
            ['id' => $ids[2], 'name' => 'three', 'categories' => [['id' => $categoryIds[3]]]],
        ], Context::createDefaultContext());

        $this->service->sync([
            new SyncOperation('delete-mapping', 'blog_category', SyncOperation::ACTION_DELETE, [], [
                ['type' => 'or', 'queries' => [
                    ['type' => 'equals', 'field' => 'categoryId', 'value' => $categoryIds[3]],
                    ['type' => 'equalsAny', 'field' => 'blogId', 'value' => [$ids[0], $ids[1]]],
                ]],
            ]),
            new SyncOperation('new-mapping', 'blog_category', SyncOperation::ACTION_UPSERT, [
                ['blogId' => $ids[0], 'categoryId' => $categoryIds[0]],
                ['blogId' => $ids[1], 'categoryId' => $categoryIds[0]],
                ['blogId' => $ids[2], 'categoryId' => $categoryIds[0]],
            ]),
        ], Context::createDefaultContext(), new SyncBehavior());

        $existing = $this->connection->fetchFirstColumn(
            'SELECT CONCAT(LOWER(HEX(blog_id)), \'-\', LOWER(HEX(category_id))) FROM blog_category WHERE blog_id IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList($ids)],
            ['ids' => ArrayParameterType::BINARY]
        );

        static::assertCount(3, $existing);
        static::assertContains($ids[0] . '-' . $categoryIds[0], $existing);
        static::assertContains($ids[1] . '-' . $categoryIds[0], $existing);
        static::assertContains($ids[2] . '-' . $categoryIds[0], $existing);
    }
}

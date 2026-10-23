<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Write;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Struct\ArrayEntity;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\BlogExtension;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedBlogDefinition;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class WriterExtensionTest extends TestCase
{
    use DataAbstractionLayerFieldTestBehaviour {
        tearDown as protected tearDownDefinitions;
    }
    use IntegrationTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = static::getContainer()->get(Connection::class);

        $this->registerDefinition(ExtendedBlogDefinition::class);
        $this->registerDefinitionWithExtensions(BlogDefinition::class, BlogExtension::class);

        $this->blogRepository = static::getContainer()->get('blog.repository');

        $this->connection->rollBack();

        $this->connection->executeStatement('
            DROP TABLE IF EXISTS `extended_product`;
            CREATE TABLE `extended_product` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NULL,
                `blog_id` BINARY(16) NULL,
                `blog_version_id` BINARY(16) NOT NULL DEFAULT 0x0fa91ce3e96a4bc2be4bd9ce752c3425,
                `language_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.extended_product.id` FOREIGN KEY (`blog_id`, `blog_version_id`) REFERENCES `blog` (`id`, `version_id`),
                CONSTRAINT `fk.extended_product.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`)
            )
        ');

        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->tearDownDefinitions();
        $this->connection->rollBack();
        $this->connection->executeStatement('DROP TABLE `extended_product`');
        $this->connection->beginTransaction();

        parent::tearDown();
    }

    public function testWriteExtensionWithExtensionKey(): void
    {
        $blogId = Uuid::randomHex();
        $this->createBlog($blogId);

        $this->blogRepository->update([
            [
                'id' => $blogId,
                'extensions' => [
                    'oneToMany' => [
                        ['name' => 'test 1', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                        ['name' => 'test 2', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$blogId]);
        $criteria->addAssociation('oneToMany');

        $blog = $this->blogRepository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->get($blogId);

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->hasExtension('oneToMany'));

        $blogExtensions = $blog->getExtension('oneToMany');
        static::assertInstanceOf(EntityCollection::class, $blogExtensions);
        static::assertContainsOnlyInstancesOf(ArrayEntity::class, $blogExtensions);
        /** @var EntityCollection<ArrayEntity> $blogExtensions */
        $blogExtensions->sort(static fn (ArrayEntity $a, ArrayEntity $b) => $a->get('name') <=> $b->get('name'));

        static::assertCount(2, $blogExtensions);
        $first = $blogExtensions->first();
        static::assertInstanceOf(ArrayEntity::class, $first);
        static::assertSame('test 1', $first->get('name'));
        $last = $blogExtensions->last();
        static::assertInstanceOf(ArrayEntity::class, $last);
        static::assertSame('test 2', $last->get('name'));
    }

    public function testCanWriteExtensionWithoutExtensionKey(): void
    {
        $blogId = Uuid::randomHex();
        $this->createBlog($blogId);

        $this->blogRepository->update([
            [
                'id' => $blogId,
                'oneToMany' => [
                    ['name' => 'test 1', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                    ['name' => 'test 2', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                ],
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$blogId]);
        $criteria->addAssociation('oneToMany');

        $blog = $this->blogRepository
            ->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->get($blogId);
        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertTrue($blog->hasExtension('oneToMany'));

        $blogExtensions = $blog->getExtension('oneToMany');
        static::assertInstanceOf(EntityCollection::class, $blogExtensions);
        static::assertContainsOnlyInstancesOf(ArrayEntity::class, $blogExtensions);
        /** @var EntityCollection<ArrayEntity> $blogExtensions */
        $blogExtensions->sort(static fn (ArrayEntity $a, ArrayEntity $b) => $a->get('name') <=> $b->get('name'));

        static::assertCount(2, $blogExtensions);
        $first = $blogExtensions->first();
        static::assertInstanceOf(ArrayEntity::class, $first);
        static::assertSame('test 1', $first->get('name'));
        $last = $blogExtensions->last();
        static::assertInstanceOf(ArrayEntity::class, $last);
        static::assertSame('test 2', $last->get('name'));
    }

    private function createBlog(string $blogId): void
    {
        $this->blogRepository->create([
            [
                'id' => $blogId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test blog',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => [
                    'id' => Uuid::randomHex(),
                    'name' => 'Contena',
                    'link' => 'https://contena.cn',
                ],
            ],
        ], Context::createDefaultContext());
    }
}

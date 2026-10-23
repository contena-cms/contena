<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer;

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
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Struct\ArrayEntity;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\BlogExtension;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\BlogExtensionSelfReferenced;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedBlogDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ManyToOneBlogDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\OneToOneInheritedBlogDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\OneToOneInheritedBlogExtension;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ToOneBlogExtension;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageEntity;

/**
 * @internal
 */
class EntityExtensionReadTest extends TestCase
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
        $this->registerDefinition(ManyToOneBlogDefinition::class);
        $this->registerDefinition(OneToOneInheritedBlogDefinition::class);
        $this->registerDefinitionWithExtensions(
            BlogDefinition::class,
            BlogExtension::class,
            BlogExtensionSelfReferenced::class,
            ToOneBlogExtension::class,
            OneToOneInheritedBlogExtension::class
        );

        $this->blogRepository = static::getContainer()->get('blog.repository');

        $this->connection->rollBack();

        $this->connection->executeStatement('
            DROP TABLE IF EXISTS `extended_product`;
            CREATE TABLE `extended_product` (
                `id` BINARY(16) NOT NULL,
                `name` VARCHAR(255) NULL,
                `blog_id` BINARY(16) NULL,
                `blog_version_id` BINARY(16) NULL DEFAULT 0x0fa91ce3e96a4bc2be4bd9ce752c3425,
                `language_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                CONSTRAINT `fk.extended_product.id` FOREIGN KEY (`blog_id`, `blog_version_id`) REFERENCES `blog` (`id`, `version_id`),
                CONSTRAINT `fk.extended_product.language_id` FOREIGN KEY (`language_id`) REFERENCES `language` (`id`)
            )
        ');

        $this->connection->executeStatement('
            DROP TABLE IF EXISTS `many_to_one_product`;
            CREATE TABLE `many_to_one_product` (
                `id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`)
            )
        ');

        $this->connection->executeStatement('
            DROP TABLE IF EXISTS `blog_one_to_one_inherited`;
            CREATE TABLE `blog_one_to_one_inherited` (
                `id` BINARY(16) NOT NULL,
                `version_id` BINARY(16) NOT NULL,
                `blog_id` BINARY(16) NOT NULL,
                `blog_version_id` BINARY(16) NOT NULL,
                `my_date` DATETIME(3) DEFAULT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`, `version_id`),
                CONSTRAINT `fk.one_to_one_product_inherited.blog_id__product_version_id` FOREIGN KEY (`blog_id`, `blog_version_id`)
                    REFERENCES `blog` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE
            )
        ');

        $this->connection->executeStatement('
            ALTER TABLE `blog`
                ADD COLUMN `linked_product_id` binary(16) NULL,
                ADD COLUMN `linked_product_version_id` binary(16) NULL,
                ADD COLUMN `many_to_one_id` binary(16) NULL,
                ADD COLUMN `oneToOneInherited` binary(16) NULL,
                ADD CONSTRAINT `fk.blog.many_to_one_id` FOREIGN KEY (`many_to_one_id`) REFERENCES `many_to_one_product` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
        ');

        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->tearDownDefinitions();
        $this->connection->rollBack();
        $this->connection->executeStatement('
            ALTER TABLE `blog`
            DROP FOREIGN KEY `fk.blog.many_to_one_id`;
        ');
        $this->connection->executeStatement('DROP TABLE `extended_product`');
        $this->connection->executeStatement('DROP TABLE `many_to_one_product`');
        $this->connection->executeStatement('DROP TABLE `blog_one_to_one_inherited`');
        $this->connection->executeStatement('
            ALTER TABLE `blog`
            DROP COLUMN `linked_product_id`,
            DROP COLUMN `linked_product_version_id`,
            DROP COLUMN `many_to_one_id`,
            DROP COLUMN `oneToOneInherited`;
        ');
        $this->connection->beginTransaction();

        parent::tearDown();
    }

    public function testICanAddAManyToOneAsExtension(): void
    {
        $blogId = Uuid::randomHex();
        $extendableId = Uuid::randomHex();

        $this->blogRepository->create([
            [
                'id' => $blogId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test blog',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manyToOne' => [
                    'id' => $extendableId,
                ],
            ],
        ], Context::createDefaultContext());

        $created = $this->connection->fetchAllAssociative('SELECT * FROM many_to_one_product');

        static::assertCount(1, $created);
        $reference = array_shift($created);
        static::assertIsArray($reference);
        static::assertSame($extendableId, Uuid::fromBytesToHex($reference['id']));

        $criteria = new Criteria([$blogId]);
        $criteria->addAssociation('manyToOne');

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertSame($blogId, $blog->getId());

        static::assertTrue($blog->hasExtension('manyToOne'));
        $extension = $blog->getExtension('manyToOne');

        static::assertInstanceOf(ArrayEntity::class, $extension);
        static::assertSame($extendableId, $extension->get('id'));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('manyToOne.id', $extendableId));

        $blogs = $this->blogRepository->searchIds($criteria, Context::createDefaultContext());
        static::assertTrue($blogs->has($blogId));
    }

    public function testICanReadManyToOneOverAssociation(): void
    {
        $blogId = Uuid::randomHex();
        $extendableId = Uuid::randomHex();

        $this->blogRepository->create([
            [
                'id' => $blogId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test blog',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manyToOne' => [
                    'id' => $extendableId,
                ],
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$extendableId]);
        $criteria->addAssociation('blogs');

        $manyToOneRepo = static::getContainer()->get('many_to_one_product.repository');
        static::assertInstanceOf(EntityRepository::class, $manyToOneRepo);
        $manyToOne = $manyToOneRepo->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(ArrayEntity::class, $manyToOne);
        static::assertCount(1, $manyToOne->get('blogs'));
    }

    public function testICanReadNestedAssociationsFromToOneExtensions(): void
    {
        $blogId = Uuid::randomHex();

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
                'toOne' => [
                    'name' => 'test',
                ],
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$blogId]);
        $criteria->addAssociation('toOne.toOne');

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->get($blogId);
        static::assertNotNull($blog);
        static::assertTrue($blog->hasExtension('toOne'));

        $extension = $blog->getExtension('toOne');
        static::assertInstanceOf(ArrayEntity::class, $extension);
        static::assertInstanceOf(BlogEntity::class, $extension->get('toOne'));
    }

    public function testICanReadNestedAssociationsFromToManyExtensions(): void
    {
        $blogId = Uuid::randomHex();

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
                'oneToMany' => [
                    ['name' => 'test 1', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                    ['name' => 'test 2', 'languageId' => Defaults::LANGUAGE_SYSTEM],
                ],
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$blogId]);
        $criteria->addAssociation('oneToMany.language');

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->get($blogId);
        static::assertNotNull($blog);

        static::assertTrue($blog->hasExtension('oneToMany'));

        $blogExtensions = $blog->getExtension('oneToMany');
        static::assertInstanceOf(EntityCollection::class, $blogExtensions);
        static::assertCount(2, $blogExtensions);

        $blogExtension = $blogExtensions->first();
        static::assertNotNull($blogExtension);
        static::assertInstanceOf(LanguageEntity::class, $blogExtension->get('language'));
    }

    public function testReadSelfReferencedAssociationsFromToManyExtensions(): void
    {
        $linkedBlogId = Uuid::randomHex();

        $this->blogRepository->create([
            [
                'id' => $linkedBlogId,
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

        $blogId = Uuid::randomHex();

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
                'linkedBlogId' => $linkedBlogId,
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$blogId]);
        $criteria->addAssociation('ManyToOneSelfReference');
        $criteria->addAssociation('ManyToOneSelfReferenceAutoload');

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->get($blogId);
        static::assertNotNull($blog);

        // Self Reference without autoload should be loaded
        static::assertTrue($blog->hasExtension('ManyToOneSelfReference'));
        // Self Reference with autoload should NOT be loaded
        static::assertFalse($blog->hasExtension('ManyToOneSelfReferenceAutoload'));

        $blogExtension = $blog->getExtension('ManyToOneSelfReference');
        static::assertInstanceOf(BlogEntity::class, $blogExtension);
        static::assertSame($linkedBlogId, $blogExtension->getVars()['id']);
    }

    public function testVariantDoesNotInheritOneToOneAssociationFromParent(): void
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2021-11-03 13:37:00');
        static::assertInstanceOf(\DateTimeImmutable::class, $date);
        $myDate = $date->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $context = Context::createDefaultContext();

        $this->blogRepository->create([
            [
                'id' => $blogId = Uuid::randomHex(),
                'blogNumber' => 'SW10001',
                'stock' => 1,
                'name' => 'Test blog',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'oneToOneInherited' => [
                    'myDate' => $myDate,
                ],
            ],
        ], $context);

        $this->blogRepository->create([
            [
                'id' => $variantId = Uuid::randomHex(),
                'blogNumber' => 'SW10001.1',
                'parentId' => $blogId,
                'stock' => 1,
                'type' => BlogDefinition::TYPE_PHYSICAL,
            ],
        ], $context);

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $context)->getEntities()->first();
        static::assertNotNull($blog);

        $variant = $context->enableInheritance(fn (Context $context): ?BlogEntity => $this->blogRepository->search(new Criteria([$variantId]), $context)->getEntities()->first());
        static::assertNotNull($variant);

        static::assertTrue($blog->hasExtension('oneToOneInherited'));
        $blogExtension = $blog->getExtension('oneToOneInherited');
        static::assertInstanceOf(ArrayEntity::class, $blogExtension);
        $actualBlogDate = $blogExtension->get('myDate');
        static::assertInstanceOf(\DateTimeImmutable::class, $actualBlogDate);
        static::assertSame($myDate, $actualBlogDate->format(Defaults::STORAGE_DATE_TIME_FORMAT));

        // This should be true
        static::assertTrue($variant->hasExtension('oneToOneInherited'));
        $variantExtension = $variant->getExtension('oneToOneInherited');
        static::assertInstanceOf(ArrayEntity::class, $variantExtension);
        // This should equal the parent blog's one-to-one-associated myDate
        $actualVariantDate = $variantExtension->get('myDate');
        static::assertInstanceOf(\DateTimeImmutable::class, $actualVariantDate);
        static::assertSame($myDate, $actualVariantDate->format(Defaults::STORAGE_DATE_TIME_FORMAT));
    }
}

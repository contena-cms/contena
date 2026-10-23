<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Write;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogManufacturer\BlogManufacturerEntity;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Media\MediaEntity;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Contena\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\VersionManager;
use Contena\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\WriteTypeIntendException;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Test\DataAbstractionLayer\Write\NonUuidFkField\NonUuidFkFieldSerializer;
use Contena\Core\Framework\Test\DataAbstractionLayer\Write\NonUuidFkField\TestEntityOneDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Write\NonUuidFkField\TestEntityTwoDefinition;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Tax\TaxDefinition;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class EntityWriterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private string $id;

    private Connection $connection;

    private string $idBytes;

    protected function setUp(): void
    {
        $this->id = Uuid::randomHex();
        $this->idBytes = Uuid::fromHexToBytes($this->id);

        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testDelete(): void
    {
        $id = Uuid::randomHex();

        $context = $this->createWriteContext();

        $this->getWriter()->insert(
            static::getContainer()->get(CategoryDefinition::class),
            [
                ['id' => $id, 'name' => 'test-country'],
            ],
            $context
        );

        $exists = $this->connection->fetchAllAssociative('SELECT * FROM category WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);
        static::assertNotCount(0, $exists);

        $deleteResult = $this->getWriter()->delete(
            static::getContainer()->get(CategoryDefinition::class),
            [
                ['id' => $id],
            ],
            $context
        );

        $exists = $this->connection->fetchAllAssociative('SELECT * FROM category WHERE id = :id', ['id' => Uuid::fromHexToBytes($id)]);
        static::assertCount(0, $exists);
        static::assertCount(0, $deleteResult->getNotFound());
        static::assertNotCount(0, $deleteResult->getDeleted());
    }

    public function testMultiDelete(): void
    {
        $id = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $context = $this->createWriteContext();

        $this->getWriter()->insert(
            static::getContainer()->get(CategoryDefinition::class),
            [
                ['id' => $id, 'name' => 'test-country1'],
                ['id' => $id2, 'name' => 'test-country2'],
            ],
            $context
        );

        $categories = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:id) ',
            ['id' => [Uuid::fromHexToBytes($id), Uuid::fromHexToBytes($id2)]],
            ['id' => ArrayParameterType::BINARY]
        );

        static::assertCount(2, $categories);

        $translations = $this->connection->fetchAllAssociative(
            'SELECT * FROM category_translation WHERE category_id IN (:id) ',
            ['id' => [Uuid::fromHexToBytes($id), Uuid::fromHexToBytes($id2)]],
            ['id' => ArrayParameterType::BINARY]
        );

        static::assertCount(2, $translations);

        $deleteResult = $this->getWriter()->delete(
            static::getContainer()->get(CategoryDefinition::class),
            [
                ['id' => $id],
                ['id' => $id2],
            ],
            $context
        );
        static::assertCount(0, $deleteResult->getNotFound());
        static::assertNotCount(0, $deleteResult->getDeleted()[CategoryDefinition::ENTITY_NAME]);

        $categories = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:id) ',
            ['id' => [Uuid::fromHexToBytes($id), Uuid::fromHexToBytes($id2)]],
            ['id' => ArrayParameterType::BINARY]
        );

        static::assertCount(0, $categories);

        $translations = $this->connection->fetchAllAssociative(
            'SELECT * FROM category_translation WHERE category_id IN (:id) ',
            ['id' => [Uuid::fromHexToBytes($id), Uuid::fromHexToBytes($id2)]],
            ['id' => ArrayParameterType::BINARY]
        );

        static::assertCount(0, $translations);
    }

    public function testMultiDeleteWithNoneExistingId(): void
    {
        $id = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $context = $this->createWriteContext();

        $this->getWriter()->insert(
            static::getContainer()->get(CategoryDefinition::class),
            [
                ['id' => $id, 'name' => 'test-country1'],
                ['id' => $id2, 'name' => 'test-country2'],
            ],
            $context
        );

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:id) ',
            ['id' => [Uuid::fromHexToBytes($id), Uuid::fromHexToBytes($id2)]],
            ['id' => ArrayParameterType::BINARY]
        );

        static::assertCount(2, $exists);

        $deleteResult = $this->getWriter()->delete(
            static::getContainer()->get(CategoryDefinition::class),
            [
                ['id' => $id],
                ['id' => $id2],
                ['id' => Uuid::randomHex()],
                ['id' => Uuid::randomHex()],
                ['id' => Uuid::randomHex()],
            ],
            $context
        );

        static::assertCount(3, $deleteResult->getNotFound()[CategoryDefinition::ENTITY_NAME]);
        static::assertCount(2, $deleteResult->getDeleted()[CategoryDefinition::ENTITY_NAME]);

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM category WHERE id IN (:id) ',
            ['id' => [Uuid::fromHexToBytes($id), Uuid::fromHexToBytes($id2)]],
            ['id' => ArrayParameterType::BINARY]
        );

        static::assertCount(0, $exists);
    }

    public function testDeleteWithMultiplePrimaryColumns(): void
    {
        $blogId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        $context = $this->createWriteContext();
        $this->getWriter()->insert(static::getContainer()->get(BlogDefinition::class), [
            [
                'id' => $blogId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test 1',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => ['name' => 'test'],
                'categories' => [
                    ['id' => $categoryId, 'name' => 'test'],
                ],
            ],
        ], $context);

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM blog_category WHERE blog_id = :blog AND category_id = :category',
            ['blog' => Uuid::fromHexToBytes($blogId), 'category' => Uuid::fromHexToBytes($categoryId)]
        );
        static::assertCount(1, $exists);

        $deleteResult = $this->getWriter()->delete(static::getContainer()->get(BlogCategoryDefinition::class), [
            ['blogId' => $blogId, 'categoryId' => $categoryId],
        ], $context);

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM blog_category WHERE blog_id = :blog AND category_id = :category',
            ['blog' => Uuid::fromHexToBytes($blogId), 'category' => Uuid::fromHexToBytes($categoryId)]
        );
        static::assertCount(0, $exists);

        static::assertCount(1, $deleteResult->getDeleted()[BlogCategoryDefinition::ENTITY_NAME]);
        static::assertCount(0, $deleteResult->getNotFound());
    }

    public function testDeleteWithMultiplePrimaryColumnsThroughCascadeDelete(): void
    {
        $blogId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        $context = $this->createWriteContext();
        $this->getWriter()->insert(static::getContainer()->get(BlogDefinition::class), [
            [
                'id' => $blogId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test 1',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => ['name' => 'test'],
                'categories' => [
                    ['id' => $categoryId, 'name' => 'test'],
                ],
            ],
        ], $context);

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM blog_category WHERE blog_id = :blog AND category_id = :category',
            ['blog' => Uuid::fromHexToBytes($blogId), 'category' => Uuid::fromHexToBytes($categoryId)]
        );
        static::assertCount(1, $exists);

        $deleteResult = $this->getWriter()->delete(static::getContainer()->get(BlogDefinition::class), [
            ['id' => $blogId],
        ], $context);

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM blog_category WHERE blog_id = :blog AND category_id = :category',
            ['blog' => Uuid::fromHexToBytes($blogId), 'category' => Uuid::fromHexToBytes($categoryId)]
        );
        static::assertCount(0, $exists);

        static::assertCount(1, $deleteResult->getDeleted()[BlogCategoryDefinition::ENTITY_NAME]);
        static::assertCount(0, $deleteResult->getNotFound());
    }

    public function testRequiresAllPrimaryKeyValuesForDelete(): void
    {
        $this->expectException(DataAbstractionLayerException::class);

        $blogId = Uuid::randomHex();

        $this->getWriter()->delete(static::getContainer()->get(BlogCategoryDefinition::class), [
            ['blogId' => $blogId],
        ], $this->createWriteContext());
    }

    public function testMultiDeleteWithMultiplePrimaryColumns(): void
    {
        $blogId = Uuid::randomHex();
        $blogId2 = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        $context = $this->createWriteContext();
        $this->getWriter()->insert(static::getContainer()->get(BlogDefinition::class), [
            [
                'id' => $blogId,
                'blogNumber' => Uuid::randomHex(),
                'name' => 'test 1',
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => ['name' => 'test'],
                'categories' => [
                    ['id' => $categoryId, 'name' => 'test'],
                ],
            ],
            [
                'id' => $blogId2,
                'blogNumber' => Uuid::randomHex(),
                'name' => 'test 1',
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => ['name' => 'test'],
                'categories' => [
                    ['id' => $categoryId],
                ],
            ],
            [
                'blogNumber' => Uuid::randomHex(),
                'name' => 'test 1',
                'stock' => 1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                'tax' => ['name' => 'test', 'taxRate' => 5],
                'manufacturer' => ['name' => 'test'],
                'categories' => [
                    ['name' => 'test'],
                ],
            ],
        ], $context);

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM blog_category WHERE blog_id IN (:blog) AND category_id = :category',
            ['blog' => [Uuid::fromHexToBytes($blogId), Uuid::fromHexToBytes($blogId2)], 'category' => Uuid::fromHexToBytes($categoryId)],
            ['blog' => ArrayParameterType::BINARY]
        );
        static::assertCount(2, $exists);

        $deleteResult = $this->getWriter()->delete(static::getContainer()->get(BlogCategoryDefinition::class), [
            ['blogId' => $blogId, 'categoryId' => $categoryId],
            ['blogId' => $blogId2, 'categoryId' => $categoryId],
        ], $context);

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM blog_category WHERE blog_id IN (:blog) AND category_id = :category',
            ['blog' => [Uuid::fromHexToBytes($blogId), Uuid::fromHexToBytes($blogId2)], 'category' => Uuid::fromHexToBytes($categoryId)],
            ['blog' => ArrayParameterType::BINARY]
        );
        static::assertCount(0, $exists);

        static::assertCount(2, $deleteResult->getDeleted()[BlogCategoryDefinition::ENTITY_NAME]);
        static::assertCount(0, $deleteResult->getNotFound());
    }

    public function testInsertWithId(): void
    {
        $this->getWriter()->insert(
            static::getContainer()->get(BlogDefinition::class),
            [
                [
                    'id' => $this->id,
                    'blogNumber' => Uuid::randomHex(),
                    'name' => 'test',
                    'stock' => 1,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                    'the_unknown_field' => 'do nothing?',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'manufacturer' => ['id' => Uuid::randomHex(), 'link' => 'https://contena.cn', 'name' => 'Contena'],
                    'mode' => 0,
                    'lastStock' => true,
                    'crossbundlelook' => 1,
                    'notification' => true,
                    'template' => 'foo',
                    'updatedAt' => new \DateTime(),
                    'active' => true,
                ],
            ],
            $this->createWriteContext()
        );

        $blog = $this->connection->fetchAssociative('SELECT * FROM blog WHERE id=:id', [
            'id' => $this->idBytes,
        ]);

        static::assertIsArray($blog);
        static::assertSame($this->idBytes, $blog['id']);
    }

    public function testInsertWithoutId(): void
    {
        $blogCountBefore = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM blog');

        $this->getWriter()->insert(
            static::getContainer()->get(BlogDefinition::class),
            [
                [
                    'blogNumber' => Uuid::randomHex(),
                    'the_unknown_field' => 'do nothing?',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'name' => 'foo',
                    'stock' => 1,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                    'manufacturer' => ['id' => Uuid::randomHex(), 'link' => 'https://contena.cn', 'name' => 'Contena'],
                ],
            ],
            $this->createWriteContext()
        );

        $blogCountAfter = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM blog');

        static::assertSame($blogCountBefore + 1, $blogCountAfter);
    }

    public function testInsertFromDocs(): void
    {
        $this->getWriter()->insert(
            static::getContainer()->get(BlogDefinition::class),
            [
                [
                    'id' => $this->id,
                    'blogNumber' => Uuid::randomHex(),
                    'name' => 'ConfiguratorTest',
                    'description' => 'A test article',
                    'stock' => 1,
                    'descriptionLong' => '<p>I\'m a <b>test article</b></p>',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'manufacturer' => ['id' => Uuid::randomHex(), 'link' => 'https://contena.cn', 'name' => 'Contena'],
                    'updatedAt' => new \DateTime(),
                    'mode' => 0,
                    'lastStock' => true,
                    'crossbundlelook' => 1,
                    'notification' => false,
                    'template' => 'foo',
                    'active' => true,
                    'additionaltext' => 'S / Schwarz',
                    'inStock' => 15,
                    'isMain' => true,
                    'categories' => [
                        ['name' => 'Some category'],
                    ],
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                ],
            ],
            $this->createWriteContext()
        );

        $blog = $this->connection->fetchAssociative('SELECT * FROM blog WHERE id=:id', [
            'id' => $this->idBytes,
        ]);

        static::assertNotFalse($blog);
    }

    public function testUpdate(): void
    {
        $this->insertEmptyBlog();

        $blogManufacturerId = Uuid::randomHex();

        $this->getWriter()->update(
            static::getContainer()->get(BlogDefinition::class),
            [
                [
                    'id' => $this->id,
                    'name' => '_THE_TITLE_',
                    'stock' => 1,
                    'the_unknown_field' => 'do nothing?',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'description' => '<p>html</p>',
                    'availableFrom' => new \DateTime('2011-01-01T15:03:01.012345Z'),
                    'availableTo' => new \DateTime('2011-01-01T15:03:01.012345Z'),
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.10, 'linked' => false]],
                    'manufacturer' => [
                        'id' => $blogManufacturerId,
                        'link' => 'http://www.contena.cn',
                        'name' => 'Another Company',
                    ],
                ],
            ],
            $this->createWriteContext()
        );

        $blogManufacturer = $this->connection->fetchAssociative('SELECT * FROM blog_manufacturer WHERE id=:id', ['id' => Uuid::fromHexToBytes($blogManufacturerId)]);
        static::assertIsArray($blogManufacturer);
        $blogManufacturerTranslation = $this->connection->fetchAssociative('SELECT * FROM blog_manufacturer_translation WHERE blog_manufacturer_id=:id', ['id' => Uuid::fromHexToBytes($blogManufacturerId)]);
        static::assertIsArray($blogManufacturerTranslation);
        $blogTranslation = $this->connection->fetchAssociative('SELECT * FROM blog_translation WHERE blog_id=:id', ['id' => $this->idBytes]);
        static::assertIsArray($blogTranslation);

        static::assertSame('_THE_TITLE_', $blogTranslation['name'], print_r($blogTranslation, true));
        static::assertSame('<p>html</p>', $blogTranslation['description']);
        static::assertSame('Another Company', $blogManufacturerTranslation['name']);
        static::assertSame('http://www.contena.cn', $blogManufacturerTranslation['link']);
    }

    public function testUpdateWritesDefaultColumnsIfOmmitted(): void
    {
        $this->insertEmptyBlog();

        $newBlog = $this->connection->fetchAssociative('SELECT * FROM blog WHERE id=:id', ['id' => $this->idBytes]);
        static::assertIsArray($newBlog);

        $this->getWriter()->update(
            static::getContainer()->get(BlogDefinition::class),
            [
                ['id' => $this->id, 'ean' => 'ABC'],
            ],
            $this->createWriteContext()
        );

        $blog = $this->connection->fetchAssociative('SELECT * FROM blog WHERE id=:id', ['id' => $this->idBytes]);
        static::assertIsArray($blog);

        static::assertSame('ABC', $blog['ean']);

        static::assertNotSame('0000-00-00 00:00:00', $blog['updated_at']);
        static::assertNotSame('2011-01-01 15:03:01', $blog['updated_at']);

        static::assertNotSame('0000-00-00 00:00:00', $blog['created_at']);
        static::assertNotSame('2011-01-01 15:03:01', $blog['created_at']);
        static::assertNotSame('0000-00-00 00:00:00', $newBlog['created_at']);
        static::assertNotSame('2011-01-01 15:03:01', $newBlog['created_at']);
    }

    public function testInsertIgnoresRuntimeFields(): void
    {
        static::assertNotNull(
            static::getContainer()->get(MediaDefinition::class)
                ->getFields()
                ->get('url')
                ?->getFlag(Runtime::class)
        );

        $id = '2b9a945bb62b4122a32a3bbfbe1e6fd3';
        $writeContext = $this->createWriteContext();
        $this->getWriter()->insert(
            static::getContainer()->get(MediaDefinition::class),
            [
                [
                    'id' => $id,
                    'name' => 'Test media',
                    'fileName' => 'testFile',
                    'mimeType' => 'image/jpeg',
                    'fileExtension' => 'jpg',
                    'path' => 'testFile.jpg',
                    'url' => 'www.example.com',
                ],
            ],
            $writeContext
        );

        $media = $this->getMediaRepository()->search(
            new Criteria([$id]),
            Context::createDefaultContext()
        )->getEntities()->get($id);

        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertStringContainsString('/testFile.jpg', $media->getUrl());
    }

    public function testUpdateIgnoresRuntimeFields(): void
    {
        static::assertNotNull(static::getContainer()->get(MediaDefinition::class)->getFields()->get('url')?->getFlag(Runtime::class));
        $id = '2b9a945bb62b4122a32a3bbfbe1e6fd3';
        $writeContext = $this->createWriteContext();
        $this->getWriter()->insert(
            static::getContainer()->get(MediaDefinition::class),
            [
                [
                    'id' => $id,
                    'name' => 'Test media',
                    'fileName' => 'testFile',
                    'path' => 'testFile.jpg',
                    'mimeType' => 'image/jpeg',
                    'fileExtension' => 'jpg',
                ],
            ],
            $writeContext
        );

        $this->getWriter()->update(
            static::getContainer()->get(MediaDefinition::class),
            [
                ['id' => $id, 'url' => 'www.example.com'],
            ],
            $this->createWriteContext()
        );

        $media = $this->getMediaRepository()->search(
            new Criteria([$id]),
            Context::createDefaultContext()
        )->getEntities()->get($id);

        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertStringContainsString('/testFile.jpg', $media->getUrl());
    }

    public function testUpdateWritesMultipleTranslations(): void
    {
        $this->insertEmptyBlog();

        $localeId = Uuid::randomHex();
        static::getContainer()->get('locale.repository')->upsert([
            ['id' => $localeId, 'name' => 'test', 'territory' => 'tmp', 'code' => 'de-DE-' . Uuid::randomHex()],
        ], Context::createDefaultContext());

        static::getContainer()->get('language.repository')->upsert([
            [
                'id' => '2d905256e75149678dd5a32a81b94f1f',
                'name' => 'language 2',
                'localeId' => $localeId,
                'localeVersionId' => Defaults::LIVE_VERSION,
                'active' => true,
                'translationCode' => [
                    'code' => 'de-DE-' . Uuid::randomHex(),
                    'name' => 'test name',
                    'territory' => 'test territory',
                ],
            ],
        ], Context::createDefaultContext());

        $this->getWriter()->update(
            static::getContainer()->get(BlogDefinition::class),
            [
                [
                    'id' => $this->id,
                    'stock' => 1,
                    'name' => [
                        Defaults::LANGUAGE_SYSTEM => '1ABC',
                        '2d905256e75149678dd5a32a81b94f1f' => '2ABC',
                    ],
                    'description' => 'foo', // implicit FFA32A50E2D04CF38389A53F8D6CD594
                    'translations' => [
                        '2d905256e75149678dd5a32a81b94f1f' => [
                            'name' => 'bar',
                            'description' => 'foo',
                            'keywords' => 'fiz,baz',
                        ],
                    ],
                    'metaTitle' => [
                        '2d905256e75149678dd5a32a81b94f1f' => 'bar',
                    ],
                ],
            ],
            $this->createWriteContext()
        );

        $blog = $this->connection->fetchAssociative('SELECT * FROM blog WHERE id=:id', ['id' => $this->idBytes]);
        static::assertIsArray($blog);

        $blogTranslations = $this->connection->fetchAllAssociative('SELECT * FROM blog_translation WHERE blog_id= :id', ['id' => $this->idBytes]);

        static::assertNotCount(0, $blog);

        static::assertCount(2, $blogTranslations, print_r($blogTranslations, true));

        $blogTranslations = array_map(static function ($a) {
            $a['language_id'] = Uuid::fromBytesToHex($a['language_id']);

            return $a;
        }, $blogTranslations);

        foreach ($blogTranslations as $translation) {
            if ($translation['language_id'] === Defaults::LANGUAGE_SYSTEM) {
                static::assertSame('1ABC', $translation['name']);
                static::assertSame('foo', $translation['description']);
                static::assertNull($translation['meta_title']);
                static::assertNull($translation['keywords']);
            } else {
                static::assertSame('bar', $translation['name']);
                static::assertSame('foo', $translation['description']);
                static::assertSame('bar', $translation['meta_title']);
                static::assertSame('fiz,baz', $translation['keywords']);
            }
        }
    }

    public function testUpdateInvalid(): void
    {
        $this->insertEmptyBlog();

        $tooLongValue = '';
        for ($i = 0; $i < 512; ++$i) {
            $tooLongValue .= '#';
        }

        $this->expectException(WriteException::class);
        $this->getWriter()->update(
            static::getContainer()->get(BlogDefinition::class),
            [
                ['id' => $this->id, 'name' => $tooLongValue],
            ],
            $this->createWriteContext()
        );
    }

    public function testInsertWithOnlyRequiredTranslated(): void
    {
        $id = Uuid::randomHex();
        $data = ['id' => $id];

        $this->expectException(WriteTypeIntendException::class);
        $this->getWriter()->update(
            static::getContainer()->get(TaxDefinition::class),
            [$data],
            WriteContext::createFromContext(Context::createDefaultContext())
        );
    }

    public function testWriteOneToManyWithOptionalIdField(): void
    {
        $mediaRepo = static::getContainer()->get('media.repository');
        $mediaId = Uuid::randomHex();
        $manufacturerId = Uuid::randomHex();

        $mediaRepo->upsert([
            [
                'id' => $mediaId,
                'name' => 'media',
                'blogManufacturers' => [
                    [
                        'id' => $manufacturerId,
                        'name' => 'test',
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $manufacturer = static::getContainer()->get('blog_manufacturer.repository')
            ->search(new Criteria([$manufacturerId]), Context::createDefaultContext())
            ->getEntities()->get($manufacturerId);

        static::assertNotNull($manufacturer);
        static::assertInstanceOf(BlogManufacturerEntity::class, $manufacturer);
        static::assertSame($mediaId, $manufacturer->getMediaId());
    }

    public function testWriteTranslatedEntityWithoutRequiredFieldsNotInSystemLanguage(): void
    {
        $mediaRepo = static::getContainer()->get('media.repository');
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $context = new Context(
            $context->getSource(),
            $context->getRuleIds(),
            $context->getCurrencyId(),
            [$this->getDeDeLanguageId(), Defaults::LANGUAGE_SYSTEM]
        );

        $mediaRepo->create([
            [
                'id' => $mediaId,
                'name' => 'media',
            ],
        ], $context);

        static::assertCount(1, $mediaRepo->search(new Criteria([$mediaId]), $context)->getEntities());
    }

    public function testWriteWithEmptyDataIsValid(): void
    {
        $blogRepository = static::getContainer()->get('blog.repository');

        $context = Context::createDefaultContext();

        $exceptionThrown = false;

        try {
            $blogRepository->create([], $context);
            $blogRepository->upsert([], $context);
            $blogRepository->update([], $context);
        } catch (\InvalidArgumentException) {
            $exceptionThrown = true;
        }

        static::assertFalse($exceptionThrown);
    }

    public function testCanWriteReadAndDeleteEntitiesWithFKFieldValuesThatAreNotUuids(): void
    {
        // Because this test creates new database tables we need to commit the current transaction. Because table
        // creation auto-commits the current transaction on database level and would cause errors when Doctrine tries to
        // commit the still-open transaction.
        $this->connection->commit();
        $container = static::getContainer();
        $context = Context::createDefaultContext();
        /** @var DefinitionInstanceRegistry $definitionInstanceRegistry */
        $definitionInstanceRegistry = $container->get(DefinitionInstanceRegistry::class);

        // Prepare test entity 1 that has a non-uuid primary key
        $this->connection->executeStatement(
            'CREATE TABLE `test_entity_one` (
                `technical_name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`technical_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
        $definitionInstanceRegistry->register(new TestEntityOneDefinition());
        $testEntityOneRepository = new EntityRepository(
            $definitionInstanceRegistry->getByClassOrEntityName(TestEntityOneDefinition::class),
            $container->get(EntityReaderInterface::class),
            $container->get(VersionManager::class),
            $container->get(EntitySearcherInterface::class),
            $container->get(EntityAggregatorInterface::class),
            $container->get('event_dispatcher'),
            $container->get(EntityLoadedEventFactory::class)
        );

        // Prepare test entity 2 that references test entity 1
        $this->connection->executeStatement(
            'CREATE TABLE `test_entity_two` (
                `id` BINARY(16) NOT NULL,
                `test_entity_one_technical_name` VARCHAR(255) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;',
        );
        $definitionInstanceRegistry->register(new TestEntityTwoDefinition());
        $testEntityTwoRepository = new EntityRepository(
            $definitionInstanceRegistry->getByClassOrEntityName(TestEntityTwoDefinition::class),
            $container->get(EntityReaderInterface::class),
            $container->get(VersionManager::class),
            $container->get(EntitySearcherInterface::class),
            $container->get(EntityAggregatorInterface::class),
            $container->get('event_dispatcher'),
            $container->get(EntityLoadedEventFactory::class)
        );
        $container->set(NonUuidFkFieldSerializer::class, new NonUuidFkFieldSerializer());

        // Test creation
        $testEntityOneRepository->create(
            [
                [
                    'technicalName' => 'Some-Technical-Name',
                ],
            ],
            $context,
        );
        $testEntityTwoId = Uuid::randomHex();
        $testEntityTwoRepository->create(
            [
                [
                    'id' => $testEntityTwoId,
                    'testEntityOneTechnicalName' => 'Some-Technical-Name',
                ],
            ],
            $context,
        );

        // Test fetch
        $fetchOne = $testEntityOneRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', 'Some-Technical-Name')),
            $context,
        );
        static::assertCount(1, $fetchOne->getEntities());

        // Test deletion
        $testEntityOneRepository->delete([['technicalName' => 'Some-Technical-Name']], $context);
        $testEntityTwoRepository->delete([['id' => $testEntityTwoId]], $context);

        $fetchOneDeleted = $testEntityOneRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('technicalName', 'Some-Technical-Name')),
            $context,
        );
        $fetchTwoDeleted = $testEntityTwoRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('id', $testEntityTwoId)),
            $context,
        );
        static::assertCount(0, $fetchOneDeleted->getEntities());
        static::assertCount(0, $fetchTwoDeleted->getEntities());

        // Clean up
        $this->connection->executeStatement(
            'DROP TABLE IF EXISTS `test_entity_two`;
            DROP TABLE IF EXISTS `test_entity_one`;',
        );
        $this->connection->beginTransaction();
    }

    public function testCanUpdateEntitiesToAddCustomFields(): void
    {
        /** @var EntityRepository<BlogCollection> $blogRepository */
        $blogRepository = static::getContainer()->get('blog.repository');
        $blogId = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $blogRepository->create(
            [
                [
                    'id' => $blogId,
                    'name' => 'foo',
                    'blogNumber' => Uuid::randomHex(),
                    'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'tax'],
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 12, 'linked' => false]],
                    'stock' => 0,
                ],
            ],
            $context,
        );

        $blogRepository->update(
            [
                [
                    'id' => $blogId,
                    'customFields' => ['foo' => 'bar'],
                ],
            ],
            $context,
        );

        $blog = $blogRepository->search(new Criteria([$blogId]), $context)->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertIsArray($blog->getCustomFields());
    }

    public function testCloneVariantTranslation(): void
    {
        $ids = new IdsCollection();

        static::getContainer()->get('language.repository')->create(
            [
                [
                    'id' => $ids->create('language'),
                    'name' => 'test-language',
                    'localeId' => $this->getLocaleIdOfSystemLanguage(),
                    'active' => true,
                    'translationCode' => [
                        'code' => 'de-US',
                        'name' => 'Test locale',
                        'territory' => 'test',
                    ],
                ],
            ],
            Context::createDefaultContext()
        );

        $blog = (new BlogBuilder($ids, 'parent'))
            ->price(100)
            ->variant(
                (new BlogBuilder($ids, 'child'))
                    ->translation($ids->get('language'), 'description', 'update')
                    ->build()
            )
            ->build();

        static::getContainer()->get('blog.repository')->create([$blog], Context::createDefaultContext());

        $behavior = new CloneBehavior(['blogNumber' => 'new-parent'], false);

        static::getContainer()->get('blog.repository')->clone(
            $ids->get('parent'),
            Context::createDefaultContext(),
            $ids->get('new-parent'),
            $behavior
        );

        $behavior = new CloneBehavior([
            'parentId' => $ids->get('new-parent'),
            'blogNumber' => 'new-child',
        ], false);

        static::getContainer()->get('blog.repository')->clone(
            $ids->get('child'),
            Context::createDefaultContext(),
            $ids->get('new-child'),
            $behavior
        );

        $translations = static::getContainer()->get(Connection::class)->fetchAssociative(
            'SELECT name, description FROM blog_translation WHERE language_id = :language AND blog_id = :id',
            ['language' => $ids->getBytes('language'), 'id' => $ids->getBytes('new-child')]
        );

        static::assertIsArray($translations);
        static::assertNull($translations['name']);
        static::assertSame('update', $translations['description']);
    }

    protected function createWriteContext(): WriteContext
    {
        return WriteContext::createFromContext(Context::createDefaultContext());
    }

    protected function insertEmptyBlog(): void
    {
        $this->getWriter()->insert(
            static::getContainer()->get(BlogDefinition::class),
            [
                [
                    'id' => $this->id,
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
                    'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
            ],
            $this->createWriteContext()
        );
    }

    private function getWriter(): EntityWriterInterface
    {
        return static::getContainer()->get(EntityWriter::class);
    }

    /**
     * @return EntityRepository<MediaCollection>
     */
    private function getMediaRepository(): EntityRepository
    {
        return static::getContainer()->get('media.repository');
    }
}

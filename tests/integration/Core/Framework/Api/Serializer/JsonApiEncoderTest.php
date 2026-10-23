<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Serializer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Rule\RuleDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\Serializer\JsonApiEncoder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\AssociationExtension;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\CustomFieldPlainTestDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendableDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ExtendedBlogDefinition;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\BlogExtension;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\ScalarRuntimeExtension;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\AdminUser\AdminUserDefinition;
use Contena\Tests\Integration\Core\Framework\Api\Serializer\fixtures\SerializationFixture;
use Contena\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestBasicStruct;
use Contena\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestBasicWithExtension;
use Contena\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestBasicWithToManyExtension;
use Contena\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestBasicWithToManyRelationships;
use Contena\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestBasicWithToOneRelationship;
use Contena\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestCollectionWithSelfReference;
use Contena\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestCollectionWithToOneRelationship;
use Contena\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestInternalFieldsAreFiltered;
use Contena\Tests\Integration\Core\Framework\Api\Serializer\fixtures\TestMainResourceShouldNotBeInIncluded;

/**
 * @internal
 */
class JsonApiEncoderTest extends TestCase
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
        $this->registerDefinitionWithExtensions(
            BlogDefinition::class,
            BlogExtension::class
        );

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
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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

    /**
     * @return iterable<string, list<mixed>>
     */
    public static function emptyInputProvider(): iterable
    {
        yield 'empty input null' => [null];
        yield 'empty input string' => ['string'];
        yield 'empty input 1' => [1];
        yield 'empty input false' => [false];
        yield 'empty input date time' => [new \DateTime()];
        yield 'empty input 1 point 1' => [1.1];
    }

    #[DataProvider('emptyInputProvider')]
    public function testEncodeWithEmptyInput(mixed $input): void
    {
        $this->expectExceptionObject(ApiException::unsupportedEncoderInput());

        $encoder = static::getContainer()->get(JsonApiEncoder::class);
        $encoder->encode(new Criteria(), static::getContainer()->get(BlogDefinition::class), $input, SerializationFixture::API_BASE_URL);
    }

    /**
     * @return iterable<string, array{class-string<EntityDefinition>, SerializationFixture}>
     */
    public static function complexStructsProvider(): iterable
    {
        yield 'media resource with basic struct is encoded' => [MediaDefinition::class, new TestBasicStruct()];
        yield 'admin_user resource with to many relationships is encoded' => [AdminUserDefinition::class, new TestBasicWithToManyRelationships()];
        yield 'media resource with to one relationship is encoded' => [MediaDefinition::class, new TestBasicWithToOneRelationship()];
        yield 'media folder resource with self reference collection is encoded' => [MediaFolderDefinition::class, new TestCollectionWithSelfReference()];
        yield 'media resource with collection to one relationship is encoded' => [MediaDefinition::class, new TestCollectionWithToOneRelationship()];
        yield 'rule resource filters internal fields' => [RuleDefinition::class, new TestInternalFieldsAreFiltered()];
        yield 'admin_user resource keeps main resource out of included data' => [AdminUserDefinition::class, new TestMainResourceShouldNotBeInIncluded()];
    }

    /**
     * @param class-string<EntityDefinition> $definitionClass
     */
    #[DataProvider('complexStructsProvider')]
    public function testEncodeComplexStructs(string $definitionClass, SerializationFixture $fixture): void
    {
        $definition = static::getContainer()->get($definitionClass);
        static::assertInstanceOf(EntityDefinition::class, $definition);
        $encoder = static::getContainer()->get(JsonApiEncoder::class);
        $actual = $encoder->encode(new Criteria(), $definition, $fixture->getInput(), SerializationFixture::API_BASE_URL);
        $actual = json_decode((string) $actual, true, 512, \JSON_THROW_ON_ERROR);

        // remove extensions from test
        $actual = $this->arrayRemove($actual, 'extensions');
        $actual['included'] = $this->removeIncludedExtensions($actual['included']);

        $this->assertValues($fixture->getAdminJsonApiFixtures(), $actual);
    }

    /**
     * Not possible with data provider as we have to manipulate the container, but the data provider run before all tests
     */
    public function testEncodeStructWithExtension(): void
    {
        $this->registerDefinition(ExtendableDefinition::class, ExtendedDefinition::class);
        $extendableDefinition = new ExtendableDefinition();
        $extendableDefinition->addExtension(new AssociationExtension());
        $extendableDefinition->addExtension(new ScalarRuntimeExtension());

        $extendableDefinition->compile(static::getContainer()->get(DefinitionInstanceRegistry::class));
        $fixture = new TestBasicWithExtension();

        $encoder = static::getContainer()->get(JsonApiEncoder::class);
        $actual = $encoder->encode(new Criteria(), $extendableDefinition, $fixture->getInput(), SerializationFixture::API_BASE_URL);

        // check that empty "links" object is an object and not array: https://jsonapi.org/format/#document-links
        static::assertStringNotContainsString('"links":[]', $actual);
        static::assertStringContainsString('"links":{}', $actual);

        $this->assertValues($fixture->getAdminJsonApiFixtures(), json_decode((string) $actual, true, 512, \JSON_THROW_ON_ERROR));
    }

    /**
     * Not possible with data provider as we have to manipulate the container, but the data provider run before all tests
     */
    public function testEncodeStructWithToManyExtension(): void
    {
        $this->registerDefinition(ExtendableDefinition::class, ExtendedDefinition::class);
        $extendableDefinition = new ExtendableDefinition();
        $extendableDefinition->addExtension(new AssociationExtension());

        $extendableDefinition->compile(static::getContainer()->get(DefinitionInstanceRegistry::class));
        $fixture = new TestBasicWithToManyExtension();

        $encoder = static::getContainer()->get(JsonApiEncoder::class);
        $actual = $encoder->encode(new Criteria(), $extendableDefinition, $fixture->getInput(), SerializationFixture::API_BASE_URL);

        // check that empty "links" object is an object and not array: https://jsonapi.org/format/#document-links
        static::assertStringNotContainsString('"links":[]', $actual);
        static::assertStringContainsString('"links":{}', $actual);

        // check that empty "attributes" object is an object and not array: https://jsonapi.org/format/#document-resource-object-attributes
        static::assertStringNotContainsString('"attributes":[]', $actual);
        static::assertStringContainsString('"attributes":{}', $actual);

        $this->assertValues($fixture->getAdminJsonApiFixtures(), json_decode((string) $actual, true, 512, \JSON_THROW_ON_ERROR));
    }

    public function testEncodeEntityWithToOneEntityExtension(): void
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
        $criteria->addAssociation('toOne');

        $blogDefinition = static::getContainer()->get(BlogDefinition::class);

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->get($blogId);
        $encoder = static::getContainer()->get(JsonApiEncoder::class);
        $encodedResponse = $encoder->encode(new Criteria(), $blogDefinition, $blog, SerializationFixture::API_BASE_URL);
        $actual = json_decode((string) $encodedResponse, true, 512, \JSON_THROW_ON_ERROR);

        foreach ($actual['included'] as $included) {
            if ($included['type'] !== 'extension') {
                continue;
            }
            static::assertIsArray($included['relationships']['toOne']['data'], 'The relationship data to the loaded extension association is missing');
            static::assertSame('extended_product', $included['relationships']['toOne']['data']['type']);
            static::assertIsString($included['relationships']['toOne']['data']['id']);
            static::assertNotSame('', $included['relationships']['toOne']['data']['id']);
        }
    }

    public function testEncodeEntityWithToManyEntityExtension(): void
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
                    [
                        'name' => 'toMany01',
                    ],
                    [
                        'name' => 'toMany02',
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$blogId]);
        $criteria->addAssociation('oneToMany');

        $blogDefinition = static::getContainer()->get(BlogDefinition::class);

        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->get($blogId);
        $encoder = static::getContainer()->get(JsonApiEncoder::class);
        $encodedResponse = $encoder->encode(new Criteria(), $blogDefinition, $blog, SerializationFixture::API_BASE_URL);
        $actual = json_decode((string) $encodedResponse, true, 512, \JSON_THROW_ON_ERROR);

        foreach ($actual['included'] as $included) {
            if ($included['type'] !== 'extension') {
                continue;
            }
            static::assertIsArray($included['relationships']['oneToMany']['data'], 'The relationship data to the loaded extension association is missing');
            static::assertCount(2, $included['relationships']['oneToMany']['data']);
            static::assertSame('extended_product', $included['relationships']['oneToMany']['data'][0]['type']);
            static::assertIsString($included['relationships']['oneToMany']['data'][0]['id']);
            static::assertNotSame('', $included['relationships']['oneToMany']['data'][0]['id']);
        }
    }

    /**
     * @param array<mixed> $input
     * @param array<mixed>|null $output
     */
    #[DataProvider('customFieldsProvider')]
    public function testCustomFields(array $input, ?array $output): void
    {
        $encoder = static::getContainer()->get(JsonApiEncoder::class);

        $definition = new CustomFieldPlainTestDefinition();
        $definition->compile(static::getContainer()->get(DefinitionInstanceRegistry::class));
        $struct = new class extends Entity {
            use EntityCustomFieldsTrait;
        };
        $struct->setUniqueIdentifier(Uuid::randomHex());
        $struct->assign($input);

        $actual = json_decode((string) $encoder->encode(new Criteria(), $definition, $struct, SerializationFixture::API_BASE_URL), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame($output, $actual['data']['attributes']['customFields']);
    }

    public static function customFieldsProvider(): \Generator
    {
        yield 'Custom field null' => [
            [
                'customFields' => null,
            ],
            null,
        ];

        yield 'Custom field with empty array' => [
            [
                'customFields' => [],
            ],
            [],
        ];

        yield 'Custom field with values' => [
            [
                'customFields' => ['bla'],
            ],
            ['bla'],
        ];
    }

    /**
     * @param array<mixed> $haystack
     *
     * @return array<mixed>
     */
    private function arrayRemove(array $haystack, string $keyToRemove): array
    {
        foreach ($haystack as $key => $value) {
            if (\is_array($value)) {
                $haystack[$key] = $this->arrayRemove($value, $keyToRemove);
            }

            if ($key === $keyToRemove) {
                unset($haystack[$key]);
            }
        }

        return $haystack;
    }

    /**
     * @param array<array<mixed>> $array
     *
     * @return array<array<mixed>>
     */
    private function removeIncludedExtensions(array $array): array
    {
        $filtered = [];
        foreach ($array as $item) {
            if ($item['type'] !== 'extension') {
                $filtered[] = $item;
            }
        }

        return $filtered;
    }

    /**
     * @param array<mixed> $expected
     * @param array<mixed> $actual
     */
    private function assertValues(array $expected, array $actual): void
    {
        foreach ($expected as $key => $value) {
            static::assertArrayHasKey($key, $actual);

            if (\is_array($value)) {
                $this->assertValues($value, $actual[$key]);
            } else {
                static::assertSame($value, $actual[$key], 'Key: ' . $key);
            }
        }
    }
}

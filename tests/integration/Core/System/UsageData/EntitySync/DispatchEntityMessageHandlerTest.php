<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\UsageData\EntitySync;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\App\ShopId\ShopIdProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\FieldType\DateInterval;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Consent\Definition\BackendData;
use Contena\Core\System\Consent\Service\ConsentService;
use Contena\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetDefinition;
use Contena\Core\System\UsageData\EntitySync\DispatchEntityMessage;
use Contena\Core\System\UsageData\EntitySync\DispatchEntityMessageHandler;
use Contena\Core\System\UsageData\EntitySync\Operation;
use Contena\Core\System\UsageData\Services\EntityDefinitionService;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class DispatchEntityMessageHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $idsCollection;

    private Connection $connection;

    protected function setUp(): void
    {
        $client = $this->getMockHttpClient();
        $client->setResponseFactory(static function (string $method, string $url): ResponseInterface {
            if (\str_ends_with($url, '/killswitch')) {
                $body = json_encode(['killswitch' => false]);
                static::assertIsString($body);

                return new MockResponse($body);
            }

            return new MockResponse();
        });

        $this->idsCollection = new IdsCollection();
        $this->connection = static::getContainer()->get(Connection::class);

        $this->setConsentAccepted();
    }

    public function testSendsEntityDataToGateway(): void
    {
        $ids = new IdsCollection();

        $client = $this->getMockHttpClient();
        $client->setResponseFactory(static function ($method, $url, $options) use ($ids) {
            if (\str_ends_with($url, '/killswitch')) {
                $body = json_encode(['killswitch' => false]);
                static::assertIsString($body);

                return new MockResponse($body);
            }

            $shopId = static::getContainer()->get(ShopIdProvider::class)->getShopId();
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            $headers = array_values($options['headers']);

            static::assertSame(Request::METHOD_POST, $method);
            static::assertStringContainsString('/v1/entities', $url);
            static::assertContains('Contena-Shop-Id: ' . $shopId, $headers);
            static::assertContains('Content-Type: application/json', $headers);

            static::assertIsArray($payload);
            static::assertArrayHasKey('operation', $payload);
            static::assertSame(Operation::CREATE->value, $payload['operation']);

            static::assertArrayHasKey('entities', $payload);
            static::assertCount(2, $payload['entities']);

            $firstBlog = $payload['entities'][0];
            static::assertIsArray($firstBlog);
            static::assertArrayHasKey('id', $firstBlog);
            static::assertSame($ids->get('test-blog-1'), $firstBlog['id']);

            // blog.categoriesRo are not in the usage-data-allow-list.json
            static::assertArrayNotHasKey('categoriesRo', $firstBlog);

            static::assertArrayHasKey('customFieldSets', $firstBlog);
            static::assertSame($firstBlog['customFieldSets'], [
                $ids->get('test_customFieldSet_1'),
            ]);

            $secondBlog = $payload['entities'][1];
            static::assertIsArray($secondBlog);
            static::assertArrayHasKey('id', $secondBlog);
            static::assertSame($ids->get('test-blog-2'), $secondBlog['id']);

            // blog.categoriesRo are not in the usage-data-allow-list.json
            static::assertArrayNotHasKey('categoriesRo', $secondBlog);

            static::assertArrayHasKey('customFieldSets', $secondBlog);
            static::assertSame($secondBlog['customFieldSets'], [
                $ids->get('test_customFieldSet_1'),
                $ids->get('test_customFieldSet_2'),
            ]);

            return new MockResponse('', ['http_code' => 200]);
        });

        $this->addBlogDefinition();

        $this->createTestBlog($ids, 'test-blog-1');
        $this->createTestBlog($ids, 'test-blog-2');

        $this->createTestCategory($ids, 'test-category-1');
        $this->createTestCategory($ids, 'test-category-2');

        $this->insertBlogCategoryTree($ids->get('test-blog-1'), $ids->get('test-category-1'));
        $this->insertBlogCategoryTree($ids->get('test-blog-1'), $ids->get('test-category-2'));

        // blog 2 only has 1 entry
        $this->insertBlogCategoryTree($ids->get('test-blog-2'), $ids->get('test-category-2'));

        $this->createCustomFieldSet($ids->get('test_customFieldSet_1'));
        $this->createCustomFieldSet($ids->get('test_customFieldSet_2'));

        // blog 1 only has 1 entry
        $this->insertBlogCustomFieldSet($ids->get('test-blog-1'), $ids->get('test_customFieldSet_1'));
        $this->insertBlogCustomFieldSet($ids->get('test-blog-2'), $ids->get('test_customFieldSet_1'));
        $this->insertBlogCustomFieldSet($ids->get('test-blog-2'), $ids->get('test_customFieldSet_2'));

        $dispatchEntityMessage = new DispatchEntityMessage(
            'blog',
            Operation::CREATE,
            new \DateTimeImmutable(),
            [
                ['id' => $ids->get('test-blog-1')],
                ['id' => $ids->get('test-blog-2')],
            ]
        );

        $messageHandler = static::getContainer()->get(DispatchEntityMessageHandler::class);
        $messageHandler($dispatchEntityMessage);
    }

    public function testSendsTranslationEntityDataToGateway(): void
    {
        $ids = new IdsCollection();

        $client = $this->getMockHttpClient();
        $client->setResponseFactory(static function ($method, $url, $options) use ($ids) {
            if (\str_ends_with($url, '/killswitch')) {
                $body = json_encode(['killswitch' => false]);
                static::assertIsString($body);

                return new MockResponse($body);
            }

            $shopId = static::getContainer()->get(ShopIdProvider::class)->getShopId();
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            $headers = array_values($options['headers']);

            static::assertSame(Request::METHOD_POST, $method);
            static::assertStringContainsString('/v1/entities', $url);
            static::assertContains('Contena-Shop-Id: ' . $shopId, $headers);
            static::assertContains('Content-Type: application/json', $headers);

            static::assertIsArray($payload);
            static::assertArrayHasKey('operation', $payload);
            static::assertSame(Operation::CREATE->value, $payload['operation']);

            static::assertArrayHasKey('entities', $payload);
            static::assertCount(2, $payload['entities']);

            $firstBlogTranslation = $payload['entities'][0];
            static::assertIsArray($firstBlogTranslation);
            static::assertArrayNotHasKey('blogVersionId', $firstBlogTranslation);

            static::assertArrayHasKey('blogId', $firstBlogTranslation);
            static::assertSame($ids->get('test-blog-3'), $firstBlogTranslation['blogId']);

            static::assertArrayHasKey('languageId', $firstBlogTranslation);
            static::assertSame(Defaults::LANGUAGE_SYSTEM, $firstBlogTranslation['languageId']);

            $secondBlogTranslation = $payload['entities'][1];
            static::assertIsArray($secondBlogTranslation);
            static::assertArrayNotHasKey('blogVersionId', $secondBlogTranslation);

            static::assertArrayHasKey('blogId', $secondBlogTranslation);
            static::assertSame($ids->get('test-blog-4'), $secondBlogTranslation['blogId']);

            static::assertArrayHasKey('languageId', $secondBlogTranslation);
            static::assertSame(Defaults::LANGUAGE_SYSTEM, $secondBlogTranslation['languageId']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $this->addBlogDefinition();

        $this->createTestBlog($ids, 'test-blog-3');
        $this->createTestBlog($ids, 'test-blog-4');

        $dispatchEntityMessage = new DispatchEntityMessage(
            'blog_translation',
            Operation::CREATE,
            new \DateTimeImmutable(),
            [
                [
                    'blog_id' => $ids->get('test-blog-3'),
                    'blog_version_id' => Defaults::LIVE_VERSION,
                    'language_id' => Defaults::LANGUAGE_SYSTEM,
                ],
                [
                    'blog_id' => $ids->get('test-blog-4'),
                    'blog_version_id' => Defaults::LIVE_VERSION,
                    'language_id' => Defaults::LANGUAGE_SYSTEM,
                ],
            ]
        );

        $messageHandler = static::getContainer()->get(DispatchEntityMessageHandler::class);
        $messageHandler($dispatchEntityMessage);
    }

    public function testSendsUpdatedEntityDataToGateway(): void
    {
        $ids = new IdsCollection();

        $client = $this->getMockHttpClient();
        $client->setResponseFactory(static function ($method, $url, $options) {
            $shopId = static::getContainer()->get(ShopIdProvider::class)->getShopId();
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            $headers = array_values($options['headers']);

            static::assertSame(Request::METHOD_POST, $method);
            static::assertContains('Contena-Shop-Id: ' . $shopId, $headers);
            static::assertContains('Content-Type: application/json', $headers);

            static::assertIsArray($payload);
            static::assertArrayHasKey('operation', $payload);
            static::assertSame(Operation::UPDATE->value, $payload['operation']);

            static::assertArrayHasKey('entities', $payload);

            // no entities should be sent, because updated_at is in the future
            static::assertCount(0, $payload['entities']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $this->addBlogDefinition();

        $blog = (new BlogBuilder($ids, 'test-blog'))
            ->name('Testing blog')
            ->price(100)
            ->build();

        static::getContainer()->get('blog.repository')->create([$blog], Context::createDefaultContext());

        // update updated_at to be in the future
        $currentTime = new \DateTimeImmutable();
        $qb = $this->connection->createQueryBuilder();
        $qb->update('blog')
            ->set('updated_at', ':updatedAt')
            ->setParameter('updatedAt', $currentTime->add(new DateInterval('P1D'))->format(Defaults::STORAGE_DATE_TIME_FORMAT))
            ->executeStatement();

        $dispatchEntityMessage = new DispatchEntityMessage(
            'blog',
            Operation::UPDATE,
            new \DateTimeImmutable(),
            [['id' => $ids->get('test-blog')]]
        );

        // message handlers are inlined
        $messageHandler = static::getContainer()->get(DispatchEntityMessageHandler::class);
        $messageHandler($dispatchEntityMessage);
    }

    public function testHandleDeletionOfEntities(): void
    {
        $firstEntity = $this->insertEntityDeletionEntry($this->idsCollection->get('blog-entity-deletion-1'));
        $secondEntity = $this->insertEntityDeletionEntry($this->idsCollection->get('blog-entity-deletion-2'));

        $client = $this->getMockHttpClient();
        $client->setResponseFactory(static function ($method, $url, $options) use ($firstEntity, $secondEntity) {
            $shopId = static::getContainer()->get(ShopIdProvider::class)->getShopId();
            $body = gzdecode($options['body']);
            static::assertIsString($body);

            $payload = json_decode($body, true, flags: \JSON_THROW_ON_ERROR);
            $headers = array_values($options['headers']);

            static::assertSame(Request::METHOD_POST, $method);
            static::assertContains('Contena-Shop-Id: ' . $shopId, $headers);
            static::assertContains('Content-Type: application/json', $headers);

            static::assertIsArray($payload);
            static::assertArrayHasKey('operation', $payload);
            static::assertSame(Operation::DELETE->value, $payload['operation']);

            static::assertArrayHasKey('entities', $payload);
            static::assertCount(2, $payload['entities']);
            static::assertSame([
                json_decode($firstEntity['entity_ids'], true),
                json_decode($secondEntity['entity_ids'], true),
            ], $payload['entities']);

            return new MockResponse('', ['http_code' => 200]);
        });

        $this->addBlogDefinition();

        $dispatchEntityMessage = new DispatchEntityMessage(
            'blog',
            Operation::DELETE,
            new \DateTimeImmutable(),
            [
                ['id' => Uuid::fromBytesToHex($firstEntity['id'])],
                ['id' => Uuid::fromBytesToHex($secondEntity['id'])],
            ]
        );

        // message handlers are inlined
        $messageHandler = static::getContainer()->get(DispatchEntityMessageHandler::class);
        $messageHandler($dispatchEntityMessage);
    }

    private function addBlogDefinition(): void
    {
        $entityDefinitionService = static::getContainer()->get(EntityDefinitionService::class);
        $entityDefinitionService->addEntityDefinition(static::getContainer()->get(BlogDefinition::class));
        $entityDefinitionService->addEntityDefinition(static::getContainer()->get(CategoryDefinition::class));
        $entityDefinitionService->addEntityDefinition(static::getContainer()->get(CustomFieldSetDefinition::class));
    }

    /**
     * @return array{id: string, entity_name: string, entity_ids: string, deleted_at: string}
     */
    private function insertEntityDeletionEntry(string $id): array
    {
        $data = [
            'id' => Uuid::fromHexToBytes($id),
            'entity_name' => 'blog_category',
            'entity_ids' => json_encode(['blog_id' => Uuid::randomHex(), 'category_id' => Uuid::randomHex()], \JSON_THROW_ON_ERROR),
            'deleted_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        $this->connection->insert('usage_data_entity_deletion', $data);

        return $data;
    }

    private function createTestBlog(IdsCollection $idsCollection, string $blogNumber): void
    {
        $blog = (new BlogBuilder($idsCollection, $blogNumber))
            ->name('Testing blog')
            ->price(100)
            ->translation(Defaults::LANGUAGE_SYSTEM, 'title', 'my awesome blog')
            ->build();

        static::getContainer()->get('blog.repository')->create([$blog], Context::createDefaultContext());
    }

    private function createTestCategory(IdsCollection $idsCollection, string $categoryName): void
    {
        static::getContainer()->get('category.repository')
            ->create([['id' => $idsCollection->get($categoryName), 'name' => $categoryName]], Context::createDefaultContext());
    }

    private function insertBlogCategoryTree(string $blogId, string $categoryId): void
    {
        $this->connection->insert('blog_category_tree', [
            'blog_id' => Uuid::fromHexToBytes($blogId),
            'category_id' => Uuid::fromHexToBytes($categoryId),
            'blog_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'category_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ], [
            'blog_id' => ParameterType::BINARY,
            'category_id' => ParameterType::BINARY,
            'blog_version_id' => ParameterType::BINARY,
            'category_version_id' => ParameterType::BINARY,
        ]);
    }

    private function insertBlogCustomFieldSet(string $blogId, string $customFieldSetId): void
    {
        $this->connection->insert('blog_custom_field_set', [
            'blog_id' => Uuid::fromHexToBytes($blogId),
            'custom_field_set_id' => Uuid::fromHexToBytes($customFieldSetId),
            'blog_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
        ], [
            'blog_id' => ParameterType::BINARY,
            'custom_field_set_id' => ParameterType::BINARY,
            'blog_version_id' => ParameterType::BINARY,
        ]);
    }

    private function createCustomFieldSet(string $id): void
    {
        $repo = static::getContainer()->get('custom_field_set.repository');

        $firstCustomFieldsId = Uuid::randomHex();
        $secondCustomFieldsId = Uuid::randomHex();

        $attributeSet = [
            'id' => $id,
            'name' => 'test_set',
            'config' => ['description' => 'test set'],
            'customFields' => [
                [
                    'id' => $firstCustomFieldsId,
                    'name' => 'test_field_' . $firstCustomFieldsId,
                    'type' => 'int',
                ],
                [
                    'id' => $secondCustomFieldsId,
                    'name' => 'test_field_' . $secondCustomFieldsId,
                    'type' => 'string',
                ],
            ],
            'relations' => [
                [
                    'entityName' => 'blog',
                ],
                [
                    'entityName' => 'order',
                ],
            ],
        ];
        $repo->create([$attributeSet], Context::createDefaultContext());
    }

    private function setConsentAccepted(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM consent_state WHERE name = :name AND identifier = :identifier',
            ['name' => BackendData::NAME, 'identifier' => 'system']
        );

        $this->connection->executeStatement(
            'INSERT INTO consent_state (id, name, identifier, state, actor, updated_at)
            VALUES (:id, :name, :identifier, :state, :actor, :updatedAt)',
            [
                'id' => Uuid::randomBytes(),
                'name' => BackendData::NAME,
                'identifier' => 'system',
                'state' => 'accepted',
                'actor' => 'test',
                'updatedAt' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
            ['id' => ParameterType::BINARY]
        );

        static::getContainer()->get(ConsentService::class)->reset();
    }

    private function getMockHttpClient(): MockHttpClient
    {
        $client = static::getContainer()->get('contena.usage_data.gateway.client');
        static::assertInstanceOf(MockHttpClient::class, $client);

        return $client;
    }
}

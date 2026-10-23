<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogIndexer;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogIndexingMessage;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Controller\SyncController;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Contena\Core\Framework\Test\TestCaseHelper\TestUser;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class SyncControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testMultipleBlogInsert(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => static::getContainer()->get(BlogDefinition::class)->getEntityName(),
                'payload' => [
                    [
                        'id' => $id1,
                        'blogNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'manufacturer' => ['name' => 'manufacturer'],
                        'tax' => ['name' => 'tax', 'taxRate' => 15],
                        'name' => 'CREATE-1',
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                    ],
                    [
                        'id' => $id2,
                        'blogNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'manufacturer' => ['name' => 'manufacturer'],
                        'name' => 'CREATE-2',
                        'tax' => ['name' => 'tax', 'taxRate' => 15],
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], [], json_encode($data, \JSON_THROW_ON_ERROR));
        $response = $this->getBrowser()->getResponse();

        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $this->getBrowser()->request('GET', '/api/blog/' . $id1);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $this->getBrowser()->request('GET', '/api/blog/' . $id2);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $this->getBrowser()->request('DELETE', '/api/blog/' . $id1);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $this->getBrowser()->request('DELETE', '/api/blog/' . $id2);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testInsertAndUpdateSameEntity(): void
    {
        $id = Uuid::randomHex();
        $blogNumber = Uuid::randomHex();

        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => static::getContainer()->get(BlogDefinition::class)->getEntityName(),
                'payload' => [
                    [
                        'id' => $id,
                        'blogNumber' => $blogNumber,
                        'active' => true,
                        'stock' => 1,
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'name' => 'CREATE-1',
                        'manufacturer' => ['name' => 'test'],
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                    ],
                    [
                        'id' => $id,
                        'blogNumber' => $blogNumber,
                        'manufacturer' => ['name' => 'test'],
                        'stock' => 1,
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'active' => false,
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], [], json_encode($data, \JSON_THROW_ON_ERROR));
        $response = $this->getBrowser()->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $this->getBrowser()->request('GET', '/api/blog/' . $id);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $responseData = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        static::assertFalse($responseData['data']['attributes']['active']);

        $this->getBrowser()->request('DELETE', '/api/blog/' . $id);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testInsertAndLinkEntities(): void
    {
        $categoryId = Uuid::randomHex();
        $blogId = Uuid::randomHex();

        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => static::getContainer()->get(CategoryDefinition::class)->getEntityName(),
                'payload' => [
                    [
                        'id' => $categoryId,
                        'name' => $blogId,
                        'manufacturer' => ['name' => 'test'],
                    ],
                ],
            ],
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => static::getContainer()->get(BlogDefinition::class)->getEntityName(),
                'payload' => [
                    [
                        'id' => $blogId,
                        'blogNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'name' => 'PROD-1',
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                        'manufacturer' => ['name' => 'test'],
                        'categories' => [
                            ['id' => $categoryId],
                        ],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], [], json_encode($data, \JSON_THROW_ON_ERROR));

        $response = $this->getBrowser()->getResponse();
        static::assertSame(200, $response->getStatusCode());

        $this->getBrowser()->request('GET', '/api/blog/' . $blogId . '/categories');
        $response = $this->getBrowser()->getResponse();
        $responseData = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $categories = array_column($responseData['data'], 'id');

        static::assertContains($categoryId, $categories);
        static::assertCount(1, $categories, 'Category Ids should not contain: ' . print_r(array_diff($categories, [$categoryId]), true));

        $this->getBrowser()->request('DELETE', '/api/category/' . $categoryId);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $this->getBrowser()->request('DELETE', '/api/blog/' . $blogId);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testNestedInsertAndLinkAfter(): void
    {
        $blog = Uuid::randomHex();
        $blog2 = Uuid::randomHex();
        $category = Uuid::randomHex();

        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => static::getContainer()->get(BlogDefinition::class)->getEntityName(),
                'payload' => [
                    [
                        'id' => $blog,
                        'blogNumber' => Uuid::randomHex(),
                        'name' => 'PROD-1',
                        'stock' => 1,
                        'manufacturer' => ['name' => 'test'],
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'categories' => [
                            ['id' => $category, 'name' => 'NESTED-CAT-1'],
                        ],
                    ],
                    [
                        'id' => $blog2,
                        'blogNumber' => Uuid::randomHex(),
                        'name' => 'PROD-2',
                        'stock' => 1,
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                        'manufacturer' => ['name' => 'test'],
                        'categories' => [
                            ['id' => $category],
                        ],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], [], json_encode($data, \JSON_THROW_ON_ERROR));

        $this->getBrowser()->request('GET', '/api/blog/' . $blog . '/categories');
        $responseData = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $categories = array_column($responseData['data'], 'id');
        static::assertContains($category, $categories);
        static::assertCount(1, $categories);

        $this->getBrowser()->request('GET', '/api/blog/' . $blog2 . '/categories');
        $responseData = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $categories = array_column($responseData['data'], 'id');
        static::assertContains($category, $categories);
        static::assertCount(1, $categories);

        $this->getBrowser()->request('GET', '/api/category/' . $category . '/blogs/');
        $responseData = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $blogs = array_column($responseData['data'], 'id');

        static::assertContains($blog, $blogs);
        static::assertContains($blog2, $blogs);
    }

    public function testMultiDelete(): void
    {
        $blog = Uuid::randomHex();
        $blog2 = Uuid::randomHex();

        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => static::getContainer()->get(BlogDefinition::class)->getEntityName(),
                'payload' => [
                    [
                        'id' => $blog,
                        'blogNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'name' => 'PROD-1',
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                        'manufacturer' => ['name' => 'test'],
                    ],
                    [
                        'id' => $blog2,
                        'blogNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'name' => 'PROD-2',
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                        'manufacturer' => ['name' => 'test'],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], [], json_encode($data, \JSON_THROW_ON_ERROR));

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM blog WHERE id IN(:id)',
            ['id' => [Uuid::fromHexToBytes($blog), Uuid::fromHexToBytes($blog2)]],
            ['id' => ArrayParameterType::BINARY]
        );
        static::assertCount(2, $exists);

        $data = [
            [
                'action' => SyncController::ACTION_DELETE,
                'entity' => static::getContainer()->get(BlogDefinition::class)->getEntityName(),
                'payload' => [
                    ['id' => $blog],
                    ['id' => $blog2],
                ],
            ],
        ];

        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], [], json_encode($data, \JSON_THROW_ON_ERROR));

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM blog WHERE id IN (:id)',
            ['id' => [Uuid::fromHexToBytes($blog), Uuid::fromHexToBytes($blog2)]],
            ['id' => ArrayParameterType::BINARY]
        );
        static::assertCount(0, $exists);
    }

    public function testCriteriaDeleteRequiresReadPrivilegesForCriteriaSelection(): void
    {
        $victimId = Uuid::randomHex();
        $controlId = Uuid::randomHex();
        $manufacturerName = Uuid::randomHex();

        foreach ([$victimId => $manufacturerName, $controlId => Uuid::randomHex()] as $blogId => $name) {
            $this->getBrowser()->jsonRequest('POST', '/api/blog', [
                'id' => $blogId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => Uuid::randomHex(),
                'tax' => ['name' => Uuid::randomHex(), 'taxRate' => 15],
                'manufacturer' => ['name' => $name],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
            ]);

            static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        }

        TestUser::createNewTestUser($this->connection, ['blog:delete'])->authorizeBrowser($this->getBrowser());

        $this->getBrowser()->jsonRequest('POST', '/api/_action/sync', [[
            'action' => SyncController::ACTION_DELETE,
            'entity' => BlogDefinition::ENTITY_NAME,
            'criteria' => [[
                'type' => 'equals',
                'field' => 'manufacturer.name',
                'value' => $manufacturerName,
            ]],
        ]]);

        $response = $this->getBrowser()->getResponse();
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $content);
        static::assertSame(
            ['blog:read', 'blog_manufacturer:read'],
            json_decode(json_decode($content, true, flags: \JSON_THROW_ON_ERROR)['errors'][0]['detail'], true, flags: \JSON_THROW_ON_ERROR)['missingPrivileges']
        );

        $existingIds = $this->connection->fetchFirstColumn(
            'SELECT LOWER(HEX(id)) FROM blog WHERE id IN (:ids)',
            ['ids' => [Uuid::fromHexToBytes($victimId), Uuid::fromHexToBytes($controlId)]],
            ['ids' => ArrayParameterType::BINARY]
        );

        static::assertEqualsCanonicalizing([$victimId, $controlId], $existingIds);
    }

    public function testIndexingByQueueHeader(): void
    {
        $blog = Uuid::randomHex();

        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => BlogDefinition::ENTITY_NAME,
                'payload' => [
                    [
                        'id' => $blog,
                        'blogNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'name' => 'PROD-1',
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request(
            'POST',
            '/api/_action/sync',
            [],
            [],
            ['HTTP_Fail-On-Error' => 'false', 'HTTP_indexing-behavior' => EntityIndexerRegistry::USE_INDEXING_QUEUE],
            json_encode($data, \JSON_THROW_ON_ERROR)
        );

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM blog WHERE id IN(:id)',
            ['id' => [Uuid::fromHexToBytes($blog)]],
            ['id' => ArrayParameterType::BINARY]
        );

        static::assertNotCount(0, $exists);

        $queuedMessages = $this->getDispatchedMessageCount(BlogIndexingMessage::class);
        static::assertSame(1, $queuedMessages);
    }

    public function testDirectIndexing(): void
    {
        $blog = Uuid::randomHex();

        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => BlogDefinition::ENTITY_NAME,
                'payload' => [
                    [
                        'id' => $blog,
                        'blogNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'name' => 'PROD-1',
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->request(
            'POST',
            '/api/_action/sync',
            [],
            [],
            ['HTTP_Fail-On-Error' => 'false'],
            json_encode($data, \JSON_THROW_ON_ERROR)
        );

        $exists = $this->connection->fetchAllAssociative(
            'SELECT * FROM blog WHERE id IN(:id)',
            ['id' => [Uuid::fromHexToBytes($blog)]],
            ['id' => ArrayParameterType::BINARY]
        );

        static::assertNotCount(0, $exists);

        static::assertSame(0, $this->getDispatchedMessageCount(BlogIndexingMessage::class));
    }

    public function testSkipIndexer(): void
    {
        $id1 = Uuid::randomHex();
        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => BlogDefinition::ENTITY_NAME,
                'payload' => [
                    [
                        'id' => $id1,
                        'blogNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'manufacturer' => ['name' => 'test'],
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'name' => 'CREATE-1',
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                    ],
                ],
            ],
        ];

        $headers = [
            'HTTP_' . PlatformRequest::HEADER_LANGUAGE_ID => Defaults::LANGUAGE_SYSTEM,
            'HTTP_' . PlatformRequest::HEADER_INDEXING_SKIP => BlogIndexer::SEARCH_KEYWORD_UPDATER,
        ];
        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], $headers, json_encode($data, \JSON_THROW_ON_ERROR));

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());

        $connection = static::getContainer()->get(Connection::class);
        $count = (int) $connection->fetchOne('SELECT COUNT(*) FROM blog_search_keyword WHERE blog_id = ?', [Uuid::fromHexToBytes($id1)]);
        static::assertSame(0, $count, 'Search keywords should be empty as we skipped it');
    }

    public function testOnlyIndexer(): void
    {
        $id1 = Uuid::randomHex();
        $data = [
            [
                'action' => SyncController::ACTION_UPSERT,
                'entity' => BlogDefinition::ENTITY_NAME,
                'payload' => [
                    [
                        'id' => $id1,
                        'blogNumber' => Uuid::randomHex(),
                        'stock' => 1,
                        'manufacturer' => ['name' => 'test'],
                        'description' => 'This is a detailed blog used to test search indexing.',
                        'tax' => ['name' => 'test', 'taxRate' => 15],
                        'name' => 'CREATE-1',
                        'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
                        'keywords' => 'a,b,c',
                    ],
                ],
            ],
        ];

        $headers = [
            'HTTP_' . PlatformRequest::HEADER_INDEXING_ONLY => BlogIndexer::SEARCH_KEYWORD_UPDATER,
            'HTTP_' . PlatformRequest::HEADER_INDEXING_BEHAVIOR => EntityIndexerRegistry::USE_INDEXING_QUEUE,
        ];
        $this->getBrowser()->request('POST', '/api/_action/sync', [], [], $headers, json_encode($data, \JSON_THROW_ON_ERROR));

        static::assertSame(200, $this->getBrowser()->getResponse()->getStatusCode());

        // Get messsage from queue.
        $sqlMessengerMessageBody = $this->connection->fetchOne('SELECT body FROM messenger_messages WHERE headers LIKE \'%BlogIndexingMessage%\'');
        static::assertIsString($sqlMessengerMessageBody);
        $data = json_decode($sqlMessengerMessageBody, true, 512, \JSON_THROW_ON_ERROR);
        $indexerOnly = $data['context']['extensions']['indexer-only']['onlies'];
        $skip = $data['skip'];

        // Assert message is as expected.
        static::assertCount(1, $indexerOnly);
        static::assertSame(BlogIndexer::SEARCH_KEYWORD_UPDATER, $indexerOnly[0], 'Only indexer does not match passed `blog.search-keyword` indexer in message.');

        // Assert message contains skip for everything except our only indexer.
        $blogIndexerClassReflection = new \ReflectionClass(BlogIndexer::class);
        $blogIndexerClassInstance = $blogIndexerClassReflection->newInstanceWithoutConstructor();
        $blogIndexerOptions = $blogIndexerClassReflection->getMethod('getOptions')->invoke($blogIndexerClassInstance);
        $allBlogIndexerMinusSearchKeyword = array_filter($blogIndexerOptions, static function ($index) {
            return $index !== BlogIndexer::SEARCH_KEYWORD_UPDATER;
        });

        static::assertEqualsCanonicalizing(array_values($allBlogIndexerMinusSearchKeyword), array_values($skip));
    }

    public static function invalidOperationProvider(): \Generator
    {
        yield 'Invalid entity argument' => [
            'invalid-entity',
            '',
            'upsert',
            [
                ['id' => 'id1', 'name' => 'first manufacturer'],
                ['id' => 'id2', 'name' => 'second manufacturer'],
            ],
            'entity',
        ];

        yield 'Missing action argument' => [
            'missing-action',
            BlogDefinition::ENTITY_NAME,
            '',
            [
                ['id' => 'id1', 'name' => 'first manufacturer'],
                ['id' => 'id2', 'name' => 'second manufacturer'],
            ],
            'action',
        ];

        yield 'Invalid action argument' => [
            'missing-action',
            BlogDefinition::ENTITY_NAME,
            'invalid-action',
            [
                ['id' => 'id1', 'name' => 'first manufacturer'],
                ['id' => 'id2', 'name' => 'second manufacturer'],
            ],
            'action',
        ];

        yield 'Missing payload argument' => [
            'missing-action',
            BlogDefinition::ENTITY_NAME,
            'upsert',
            [],
            'payload',
        ];
    }

    /**
     * @param array<mixed> $payload
     */
    #[DataProvider('invalidOperationProvider')]
    public function testItThrows400WithInvalidSyncOperation(string $key, string $entity, string $action, array $payload, string $actor): void
    {
        $data = [
            [
                'action' => $action,
                'entity' => $entity,
                'payload' => $payload,
            ],
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/_action/sync', $data, ['HTTP_Fail-On-Error' => 'true']);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('FRAMEWORK__INVALID_SYNC_OPERATION', $content['errors'][0]['code']);
        static::assertStringContainsString($actor, $content['errors'][0]['detail']);
    }

    #[DataProvider('invalidPayloadProvider')]
    public function testInvalidPayloadIsConvertedToBadRequest(string $payload, string $message): void
    {
        $client = $this->getBrowser();

        $client->request('POST', '/api/_action/sync', content: $payload);

        $response = $client->getResponse()->getContent();
        static::assertIsString($response);

        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, (int) $response['errors'][0]['status']);
        static::assertSame($message, $response['errors'][0]['detail']);
    }

    public static function invalidPayloadProvider(): \Generator
    {
        yield 'Invalid payload format' => [
            json_encode([
                [
                    'action' => 'delete',
                    'entity' => 'blog',
                    'payload' => [
                        'test' => true,
                    ],
                ],
            ], \JSON_THROW_ON_ERROR),
            'Invalid payload. Should contain a list of associative arrays',
        ];

        yield 'Malformed JSON payload' => [
            'not a json',
            'Parameter type json is invalid.',
        ];

        yield 'Missing required keys' => [
            json_encode(['delete-mapping' => 'action:delete'], \JSON_THROW_ON_ERROR),
            'Invalid payload format. Expected an array of operations.',
        ];
    }
}

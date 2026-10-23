<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\UsageData\EntitySync;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Consent\ConsentScope;
use Contena\Core\System\Consent\ConsentStatus;
use Contena\Core\System\Consent\Definition\BackendData;
use Contena\Core\System\Consent\DTO\ConsentState;
use Contena\Core\System\Consent\Service\ConsentService;
use Contena\Core\System\UsageData\EntitySync\DispatchEntityMessage;
use Contena\Core\System\UsageData\EntitySync\IterateEntitiesQueryBuilder;
use Contena\Core\System\UsageData\EntitySync\IterateEntityMessage;
use Contena\Core\System\UsageData\EntitySync\IterateEntityMessageHandler;
use Contena\Core\System\UsageData\EntitySync\Operation;
use Contena\Core\System\UsageData\Services\EntityDefinitionService;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class IterateEntityMessageHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected function setUp(): void
    {
        /** @var MockHttpClient $client */
        $client = static::getContainer()->get('contena.usage_data.gateway.client');
        $client->setResponseFactory(static function (string $method, string $url): ResponseInterface {
            if (\str_ends_with($url, '/killswitch')) {
                $body = json_encode(['killswitch' => false]);
                static::assertIsString($body);

                return new MockResponse($body);
            }

            return new MockResponse();
        });
    }

    public function testItFetchesEverythingIfLastRunIsNotSet(): void
    {
        $this->setConsentAccepted();

        $definitionRegistry = static::getContainer()->get(DefinitionInstanceRegistry::class);

        $entityDefinitionService = static::createStub(EntityDefinitionService::class);
        $entityDefinitionService->method('getAllowedEntityDefinition')
            ->willReturn($definitionRegistry->get(BlogDefinition::class));

        $blogIds = $this->setUpBlogs();

        $messageBus = new CollectingMessageBus();

        $messageHandler = new IterateEntityMessageHandler(
            $messageBus,
            new IterateEntitiesQueryBuilder(
                $entityDefinitionService,
                static::getContainer()->get(Connection::class),
                static::getContainer()->getParameter('contena.usage_data.gateway.batch_size'),
            ),
            $this->getContainer()->get(ConsentService::class),
            $entityDefinitionService,
            static::getContainer()->get(LoggerInterface::class),
        );

        $messageHandler(new IterateEntityMessage(
            'blog',
            Operation::CREATE,
            new \DateTimeImmutable('2023-08-16'),
            null,
        ));

        $dispatchedMessages = $messageBus->getMessages();

        static::assertNotCount(0, $dispatchedMessages);

        $entitySyncMessage = $dispatchedMessages[0]->getMessage();

        static::assertInstanceOf(DispatchEntityMessage::class, $entitySyncMessage);

        static::assertSame('blog', $entitySyncMessage->entityName);
        static::assertSame([
            ['id' => $blogIds->get('blog-from-the-past')],
            ['id' => $blogIds->get('blog-created-on-last-run-date')],
            ['id' => $blogIds->get('blog-created-today')],
            ['id' => $blogIds->get('blog-updated-today')],
        ], array_values($entitySyncMessage->primaryKeys));
    }

    public function testItFetchesOnlyNewChangesIfLastRunIsSet(): void
    {
        $this->setConsentAccepted();

        $definitionRegistry = static::getContainer()->get(DefinitionInstanceRegistry::class);

        $entityDefinitionService = static::createStub(EntityDefinitionService::class);
        $entityDefinitionService->method('getAllowedEntityDefinition')
            ->willReturn($definitionRegistry->get(BlogDefinition::class));

        $blogIds = $this->setUpBlogs();

        $messageBus = new CollectingMessageBus();

        $messageHandler = new IterateEntityMessageHandler(
            $messageBus,
            new IterateEntitiesQueryBuilder(
                $entityDefinitionService,
                static::getContainer()->get(Connection::class),
                static::getContainer()->getParameter('contena.usage_data.gateway.batch_size'),
            ),
            $this->getContainer()->get(ConsentService::class),
            $entityDefinitionService,
            static::getContainer()->get(LoggerInterface::class),
        );

        $messageHandler(new IterateEntityMessage(
            'blog',
            Operation::CREATE,
            new \DateTimeImmutable('2023-08-16'),
            new \DateTimeImmutable('2023-08-01'),
        ));

        $dispatchedMessages = $messageBus->getMessages();

        static::assertNotCount(0, $dispatchedMessages);

        $entitySyncMessage = $dispatchedMessages[0]->getMessage();

        static::assertInstanceOf(DispatchEntityMessage::class, $entitySyncMessage);

        static::assertSame('blog', $entitySyncMessage->entityName);
        static::assertSame([
            ['id' => $blogIds->get('blog-created-on-last-run-date')],
            ['id' => $blogIds->get('blog-created-today')],
        ], array_values($entitySyncMessage->primaryKeys));
    }

    public function testItFetchesOnlyDeletionsUpToTheCurrentRunDate(): void
    {
        $this->setConsentAccepted();

        $definitionRegistry = static::getContainer()->get(DefinitionInstanceRegistry::class);

        $entityDefinitionService = static::createStub(EntityDefinitionService::class);
        $entityDefinitionService->method('getAllowedEntityDefinition')
            ->willReturn($definitionRegistry->get(BlogDefinition::class));

        $ids = new IdsCollection();

        $this->insertBlogDeletion($ids->get('blog-from-the-past'), (new \DateTimeImmutable())->sub(new \DateInterval('P1D')));
        $this->insertBlogDeletion($ids->get('blog-from-the-future'), (new \DateTimeImmutable())->add(new \DateInterval('P1D')));

        $messageBus = new CollectingMessageBus();

        $messageHandler = new IterateEntityMessageHandler(
            $messageBus,
            new IterateEntitiesQueryBuilder(
                $entityDefinitionService,
                static::getContainer()->get(Connection::class),
                static::getContainer()->getParameter('contena.usage_data.gateway.batch_size'),
            ),
            $this->getContainer()->get(ConsentService::class),
            $entityDefinitionService,
            static::getContainer()->get(LoggerInterface::class),
        );

        $messageHandler(new IterateEntityMessage(
            'blog',
            Operation::DELETE,
            new \DateTimeImmutable(),
            new \DateTimeImmutable('2023-08-01'),
        ));

        $dispatchedMessages = $messageBus->getMessages();

        static::assertNotCount(0, $dispatchedMessages);

        $entitySyncMessage = $dispatchedMessages[0]->getMessage();

        static::assertInstanceOf(DispatchEntityMessage::class, $entitySyncMessage);
        static::assertSame(
            [
                ['id' => $ids->get('blog-from-the-past')],
            ],
            $entitySyncMessage->primaryKeys,
        );
    }

    public function testItLogsExceptionWithTableDoesNotExistExceptionIsThrown(): void
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects($this->once())->method('error');

        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects($this->once())
            ->method('getConsentState')
            ->willReturn(new ConsentState(
                BackendData::NAME,
                ConsentScope\System::NAME,
                ConsentScope\System::NAME,
                ConsentStatus::ACCEPTED,
                'actor',
                (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ));

        $entityDefinitionService = $this->createMock(EntityDefinitionService::class);
        $entityDefinitionService->expects($this->once())
            ->method('getAllowedEntityDefinition')
            ->with('test_entity')
            ->willReturn(new TestEntityDefinition());

        $messageHandler = new IterateEntityMessageHandler(
            new CollectingMessageBus(),
            static::getContainer()->get(IterateEntitiesQueryBuilder::class),
            $consentService,
            $entityDefinitionService,
            $logger,
        );

        /** @var DefinitionInstanceRegistry $registry */
        $registry = static::getContainer()->get(DefinitionInstanceRegistry::class);
        $registry->register(new TestEntityDefinition());

        $messageHandler(new IterateEntityMessage(
            TestEntityDefinition::ENTITY_NAME,
            Operation::CREATE,
            new \DateTimeImmutable('2023-08-16'),
            new \DateTimeImmutable('2023-08-01'),
        ));
    }

    private function setUpBlogs(): IdsCollection
    {
        $ids = new IdsCollection();

        $blogs = [
            (new BlogBuilder($ids, 'blog-from-the-past'))
                ->price(2.00)
                ->build(),
            (new BlogBuilder($ids, 'blog-created-on-last-run-date'))
                ->price(2.00)
                ->build(),
            (new BlogBuilder($ids, 'blog-created-today'))
                ->price(2.00)
                ->build(),
            (new BlogBuilder($ids, 'blog-updated-today'))
                ->price(2.00)
                ->build(),
        ];

        static::getContainer()->get('blog.repository')->upsert($blogs, Context::createDefaultContext());

        $connection = static::getContainer()->get(Connection::class);

        static::assertSame(1, $connection->update(
            '`blog`',
            ['`created_at`' => '2023-05-24', '`updated_at`' => null],
            ['`blog_number`' => 'blog-from-the-past'],
        ));

        static::assertSame(1, $connection->update(
            '`blog`',
            ['`created_at`' => '2023-08-02', '`updated_at`' => null],
            ['`blog_number`' => 'blog-created-on-last-run-date'],
        ));

        static::assertSame(1, $connection->update(
            '`blog`',
            ['`created_at`' => '2023-08-03', '`updated_at`' => null],
            ['`blog_number`' => 'blog-created-today'],
        ));

        static::assertSame(1, $connection->update(
            '`blog`',
            ['`created_at`' => '2022-08-02', '`updated_at`' => '2023-08-02'],
            ['`blog_number`' => 'blog-updated-today'],
        ));

        return $ids;
    }

    private function setConsentAccepted(): void
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);

        $connection->executeStatement(
            'DELETE FROM consent_state WHERE name = :name AND identifier = :identifier',
            ['name' => BackendData::NAME, 'identifier' => 'system']
        );

        $connection->executeStatement(
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

    private function insertBlogDeletion(string $id, \DateTimeImmutable $deletedAt): void
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        $qb = $connection->createQueryBuilder();
        $qb->insert('usage_data_entity_deletion');
        $qb->values([
            'id' => ':id',
            'entity_name' => ':entity_name',
            'entity_ids' => ':entity_ids',
            'deleted_at' => ':deleted_at',
        ]);
        $statement = $connection->prepare($qb->getSQL());
        $statement->bindValue(':id', Uuid::fromHexToBytes($id), ParameterType::BINARY);
        $statement->bindValue(':entity_name', 'blog');
        $statement->bindValue(':entity_ids', \json_encode(Uuid::randomHex(), \JSON_THROW_ON_ERROR));

        // this deletion is in the future
        $statement->bindValue(':deleted_at', $deletedAt->format(Defaults::STORAGE_DATE_TIME_FORMAT));

        $statement->executeStatement();
    }
}

/**
 * @internal
 */
class TestEntityDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'test_entity';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): string
    {
        return 'test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
        ]);
    }
}

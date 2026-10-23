<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\ImportExport\Event\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\ImportExport\Event\ImportExportAfterImportBatchEvent;
use Contena\Core\Content\ImportExport\Event\ImportExportAfterImportRecordsEvent;
use Contena\Core\Content\ImportExport\Event\ImportExportBeforeImportRecordEvent;
use Contena\Core\Content\ImportExport\Event\Subscriber\UserNumberRangeSubscriber;
use Contena\Core\Content\ImportExport\ImportExportException;
use Contena\Core\Content\ImportExport\Service\UserNumberRangeConfigService;
use Contena\Core\Content\ImportExport\Service\UserNumberRangePatternMatcher;
use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Content\ImportExport\Struct\ImportResult;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\AbstractIncrementStorage;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(UserNumberRangeSubscriber::class)]
class UserNumberRangeSubscriberTest extends TestCase
{
    public function testSynchronizesInsertedUserNumber(): void
    {
        $context = Context::createDefaultContext();
        $incrementStorage = $this->createMock(AbstractIncrementStorage::class);
        $incrementStorage->expects($this->once())
            ->method('increaseToAtLeast')
            ->with('00000000000000000000000000000001', 100014);

        $subscriber = new UserNumberRangeSubscriber(
            $this->createPatternConfigService(),
            new UserNumberRangePatternMatcher(),
            $incrementStorage,
            StaticEntityRepository::of(UserCollection::class, [[]]),
        );
        $subscriber->onAfterImport($this->createBatchEvent($context, new EntityWriteResult(
            'user-id',
            ['userNumber' => '100014'],
            UserDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_INSERT,
        )));
    }

    public function testSynchronizesInsertedUserNumberAfterOneByOneImport(): void
    {
        $context = Context::createDefaultContext();
        $incrementStorage = $this->createMock(AbstractIncrementStorage::class);
        $incrementStorage->expects($this->once())
            ->method('increaseToAtLeast')
            ->with('00000000000000000000000000000001', 100014);

        $subscriber = new UserNumberRangeSubscriber(
            $this->createPatternConfigService(),
            new UserNumberRangePatternMatcher(),
            $incrementStorage,
            StaticEntityRepository::of(UserCollection::class, [[]]),
        );
        $batchEvent = $this->createBatchEvent($context, new EntityWriteResult(
            'user-id',
            ['userNumber' => '100014'],
            UserDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_INSERT,
        ));

        $subscriber->onAfterImport(new ImportExportAfterImportRecordsEvent(
            $batchEvent->getConfig(),
            $context,
            $batchEvent->getResult(),
        ));
    }

    public function testDoesNotSynchronizeUpdatedUser(): void
    {
        $context = Context::createDefaultContext();
        $incrementStorage = $this->createMock(AbstractIncrementStorage::class);
        $incrementStorage->expects($this->never())->method('increaseToAtLeast');

        $subscriber = new UserNumberRangeSubscriber(
            $this->createPatternConfigService(),
            new UserNumberRangePatternMatcher(),
            $incrementStorage,
            StaticEntityRepository::of(UserCollection::class, [[]]),
        );
        $subscriber->onAfterImport($this->createBatchEvent($context, new EntityWriteResult(
            'user-id',
            ['name' => 'Updated'],
            UserDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE,
        )));
    }

    public function testSynchronizesOnlyTheHighestIncrementInTheBatch(): void
    {
        $context = Context::createDefaultContext();
        $incrementStorage = $this->createMock(AbstractIncrementStorage::class);
        $incrementStorage->expects($this->once())
            ->method('increaseToAtLeast')
            ->with('00000000000000000000000000000001', 100015);

        $subscriber = new UserNumberRangeSubscriber(
            $this->createPatternConfigService(),
            new UserNumberRangePatternMatcher(),
            $incrementStorage,
            StaticEntityRepository::of(UserCollection::class, [[]]),
        );

        $subscriber->onAfterImport($this->createBatchEvent(
            $context,
            new EntityWriteResult(
                'user-id-3',
                ['userNumber' => '100013'],
                UserDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT,
            ),
        ));
    }

    public function testSynchronizesUpdatedUserUsingItsExistingChannel(): void
    {
        $context = Context::createDefaultContext();
        $channelId = Uuid::randomHex();
        $user = new UserEntity();
        $user->setId('user-id');
        $user->setChannelId($channelId);

        $incrementStorage = $this->createMock(AbstractIncrementStorage::class);
        $incrementStorage->expects($this->once())
            ->method('increaseToAtLeast')
            ->with('00000000000000000000000000000001', 100020);

        $subscriber = new UserNumberRangeSubscriber(
            $this->createPatternConfigService(),
            new UserNumberRangePatternMatcher(),
            $incrementStorage,
            StaticEntityRepository::of(UserCollection::class, [new UserCollection([$user])]),
        );

        $subscriber->onAfterImport($this->createBatchEvent(
            $context,
            new EntityWriteResult(
                'user-id',
                ['userNumber' => '100020'],
                UserDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE,
            ),
        ));
    }

    public function testPrefersChannelFromUpdatedPayload(): void
    {
        $context = Context::createDefaultContext();
        $existingChannelId = Uuid::randomHex();
        $updatedChannelId = Uuid::randomHex();
        $updatedConfigurationId = Uuid::randomHex();
        $user = new UserEntity();
        $user->setId('user-id');
        $user->setChannelId($existingChannelId);

        $incrementStorage = $this->createMock(AbstractIncrementStorage::class);
        $incrementStorage->expects($this->once())
            ->method('increaseToAtLeast')
            ->with($updatedConfigurationId, 100020);

        $subscriber = new UserNumberRangeSubscriber(
            $this->createPatternConfigServiceForChannels([
                $existingChannelId => ['id' => Uuid::randomHex(), 'pattern' => '{n}'],
                $updatedChannelId => ['id' => $updatedConfigurationId, 'pattern' => '{n}'],
            ]),
            new UserNumberRangePatternMatcher(),
            $incrementStorage,
            StaticEntityRepository::of(UserCollection::class, [new UserCollection([$user])]),
        );

        $subscriber->onAfterImport($this->createBatchEvent(
            $context,
            new EntityWriteResult(
                'user-id',
                ['userNumber' => '100020', 'channelId' => $updatedChannelId],
                UserDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE,
            ),
        ));
    }

    public function testSynchronizesHighestIncrementPerNumberRange(): void
    {
        $context = Context::createDefaultContext();
        $firstChannelId = Uuid::randomHex();
        $secondChannelId = Uuid::randomHex();
        $firstConfigurationId = Uuid::randomHex();
        $secondConfigurationId = Uuid::randomHex();
        $writtenValues = [];

        $incrementStorage = $this->createMock(AbstractIncrementStorage::class);
        $incrementStorage->expects($this->exactly(2))
            ->method('increaseToAtLeast')
            ->willReturnCallback(function (string $configurationId, int $value) use (&$writtenValues): void {
                $writtenValues[$configurationId] = $value;
            });

        $subscriber = new UserNumberRangeSubscriber(
            $this->createPatternConfigServiceForChannels([
                $firstChannelId => ['id' => $firstConfigurationId, 'pattern' => '{n}'],
                $secondChannelId => ['id' => $secondConfigurationId, 'pattern' => '{n}'],
            ]),
            new UserNumberRangePatternMatcher(),
            $incrementStorage,
            StaticEntityRepository::of(UserCollection::class, [[]]),
        );

        $subscriber->onAfterImport($this->createBatchEvent(
            $context,
            new EntityWriteResult(
                'user-id-3',
                ['userNumber' => '200010', 'channelId' => $secondChannelId],
                UserDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_INSERT,
            ),
        ));

        static::assertSame([
            $firstConfigurationId => 100016,
            $secondConfigurationId => 200010,
        ], $writtenValues);
    }

    public function testDoesNotSynchronizeUpdateForUnknownUser(): void
    {
        $context = Context::createDefaultContext();
        $incrementStorage = $this->createMock(AbstractIncrementStorage::class);
        $incrementStorage->expects($this->never())->method('increaseToAtLeast');

        $subscriber = new UserNumberRangeSubscriber(
            $this->createPatternConfigService(),
            new UserNumberRangePatternMatcher(),
            $incrementStorage,
            StaticEntityRepository::of(UserCollection::class, [[]]),
        );

        $subscriber->onAfterImport($this->createBatchEvent(
            $context,
            new EntityWriteResult(
                'unknown-user-id',
                ['userNumber' => '100020'],
                UserDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE,
            ),
        ));
    }

    public function testDoesNotSynchronizeDeletedUser(): void
    {
        $context = Context::createDefaultContext();
        $incrementStorage = $this->createMock(AbstractIncrementStorage::class);
        $incrementStorage->expects($this->never())->method('increaseToAtLeast');

        $subscriber = new UserNumberRangeSubscriber(
            $this->createPatternConfigService(),
            new UserNumberRangePatternMatcher(),
            $incrementStorage,
            StaticEntityRepository::of(UserCollection::class, [[]]),
        );

        $subscriber->onAfterImport($this->createBatchEvent(
            $context,
            new EntityWriteResult(
                'user-id',
                ['userNumber' => '100020'],
                UserDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_DELETE,
            ),
        ));
    }

    public function testRejectsDuplicateUserNumberInTheSameNumberRange(): void
    {
        $context = Context::createDefaultContext();
        $existingUser = new UserEntity();
        $existingUser->setId('existing-id');
        $existingUser->setUserNumber('100014');
        $existingUser->setChannelId(Uuid::randomHex());
        $channelId = $existingUser->getChannelId();

        $userRepository = StaticEntityRepository::of(UserCollection::class, [new UserCollection([$existingUser])]);
        $subscriber = new UserNumberRangeSubscriber(
            $this->createPatternConfigService(),
            new UserNumberRangePatternMatcher(),
            static::createStub(AbstractIncrementStorage::class),
            $userRepository,
        );

        $this->expectExceptionObject(ImportExportException::processingError(
            'User number "100014" is already used in the selected number range.'
        ));
        $subscriber->onBeforeImportRecord(new ImportExportBeforeImportRecordEvent(
            ['userNumber' => '100014', 'channelId' => $channelId],
            [],
            new Config([], [
                'sourceEntity' => UserDefinition::ENTITY_NAME,
                'createEntities' => true,
                'updateEntities' => false,
            ], []),
            $context,
        ));
    }

    public function testRejectsDuplicateUserNumberInTheChannelNumberRangeResolvedByAssociation(): void
    {
        $context = Context::createDefaultContext();
        $channelId = Uuid::randomHex();
        $configurationId = Uuid::randomHex();
        $existingUser = new UserEntity();
        $existingUser->setId('existing-id');
        $existingUser->setUserNumber('100014');
        $existingUser->setChannelId($channelId);

        $userRepository = StaticEntityRepository::of(UserCollection::class, [new UserCollection([$existingUser])]);
        $subscriber = new UserNumberRangeSubscriber(
            $this->createPatternConfigServiceForChannels([
                $channelId => ['id' => $configurationId, 'pattern' => '{n}'],
            ]),
            new UserNumberRangePatternMatcher(),
            static::createStub(AbstractIncrementStorage::class),
            $userRepository,
        );

        $this->expectExceptionObject(ImportExportException::processingError(
            'User number "100014" is already used in the selected number range.'
        ));
        $subscriber->onBeforeImportRecord(new ImportExportBeforeImportRecordEvent(
            [
                'userNumber' => '100014',
                'channel' => [
                    'id' => $channelId,
                ],
            ],
            [],
            new Config([], [
                'sourceEntity' => UserDefinition::ENTITY_NAME,
                'createEntities' => true,
                'updateEntities' => false,
            ], []),
            $context,
        ));
    }

    public function testAllowsAnUpdateOfItsOwnUserNumber(): void
    {
        $context = Context::createDefaultContext();
        $userId = Uuid::randomHex();
        $existingUser = new UserEntity();
        $existingUser->setId($userId);
        $existingUser->setUserNumber('100014');
        $existingUser->setChannelId(Uuid::randomHex());
        $channelId = $existingUser->getChannelId();

        $userRepository = StaticEntityRepository::of(UserCollection::class, [new UserCollection([$existingUser])]);
        $subscriber = new UserNumberRangeSubscriber(
            $this->createPatternConfigService(),
            new UserNumberRangePatternMatcher(),
            static::createStub(AbstractIncrementStorage::class),
            $userRepository,
        );

        $subscriber->onBeforeImportRecord(new ImportExportBeforeImportRecordEvent(
            [
                'id' => $userId,
                'userNumber' => '100014',
                'channelId' => $channelId,
            ],
            [],
            new Config([], [
                'sourceEntity' => UserDefinition::ENTITY_NAME,
                'createEntities' => true,
                'updateEntities' => true,
            ], []),
            $context,
        ));

        static::assertSame([], $userRepository->searches);
    }

    public function testRejectsUserNumberThatDoesNotMatchTheConfiguredPattern(): void
    {
        $context = Context::createDefaultContext();
        $subscriber = new UserNumberRangeSubscriber(
            $this->createPatternConfigService('CUSTOMER-{n}-EU'),
            new UserNumberRangePatternMatcher(),
            static::createStub(AbstractIncrementStorage::class),
            StaticEntityRepository::of(UserCollection::class, [[]]),
        );

        $this->expectExceptionObject(ImportExportException::processingError(
            'User number "CUSTOMER-100014-DE" does not match the configured user number pattern.'
        ));
        $subscriber->onBeforeImportRecord(new ImportExportBeforeImportRecordEvent(
            ['userNumber' => 'CUSTOMER-100014-DE'],
            [],
            new Config([], [
                'sourceEntity' => UserDefinition::ENTITY_NAME,
                'createEntities' => true,
                'updateEntities' => false,
            ], []),
            $context,
        ));
    }

    public function testDoesNotSynchronizeUnknownPlaceholders(): void
    {
        $context = Context::createDefaultContext();
        $incrementStorage = $this->createMock(AbstractIncrementStorage::class);
        $incrementStorage->expects($this->never())->method('increaseToAtLeast');

        $subscriber = new UserNumberRangeSubscriber(
            $this->createPatternConfigService('C-{unknown}'),
            new UserNumberRangePatternMatcher(),
            $incrementStorage,
            StaticEntityRepository::of(UserCollection::class, [[]]),
        );

        $subscriber->onAfterImport($this->createBatchEvent($context, new EntityWriteResult(
            'user-id',
            ['userNumber' => 'C-100014'],
            UserDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_INSERT,
        )));
    }

    public function testSkipsSynchronizationForUnknownPlaceholders(): void
    {
        $context = Context::createDefaultContext();
        $incrementStorage = $this->createMock(AbstractIncrementStorage::class);
        $incrementStorage->expects($this->never())->method('increaseToAtLeast');

        $subscriber = new UserNumberRangeSubscriber(
            $this->createPatternConfigService('C-{unknown}-{n}'),
            new UserNumberRangePatternMatcher(),
            $incrementStorage,
            StaticEntityRepository::of(UserCollection::class, [[]]),
        );

        $subscriber->onAfterImport($this->createBatchEvent($context, new EntityWriteResult(
            'user-id',
            ['userNumber' => 'C-any-content-100014'],
            UserDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_INSERT,
        )));
    }

    public function testRejectsImportWithUnknownPlaceholder(): void
    {
        $context = Context::createDefaultContext();
        $subscriber = new UserNumberRangeSubscriber(
            $this->createPatternConfigService('C-{unknown}-{n}'),
            new UserNumberRangePatternMatcher(),
            static::createStub(AbstractIncrementStorage::class),
            StaticEntityRepository::of(UserCollection::class, [[]]),
        );

        $this->expectExceptionObject(ImportExportException::processingError(
            'User number "C-any-content-100014" does not match the configured user number pattern.'
        ));

        $subscriber->onBeforeImportRecord(new ImportExportBeforeImportRecordEvent(
            ['userNumber' => 'C-any-content-100014'],
            [],
            new Config([], [
                'sourceEntity' => UserDefinition::ENTITY_NAME,
                'createEntities' => true,
                'updateEntities' => false,
            ], []),
            $context,
        ));
    }

    private function createBatchEvent(Context $context, EntityWriteResult ...$writeResults): ImportExportAfterImportBatchEvent
    {
        $writtenEvent = new EntityWrittenEvent(UserDefinition::ENTITY_NAME, \array_values($writeResults), $context);
        $result = new EntityWrittenContainerEvent($context, new NestedEventCollection([$writtenEvent]), []);

        return new ImportExportAfterImportBatchEvent(
            new Config([], ['sourceEntity' => UserDefinition::ENTITY_NAME], []),
            $context,
            new ImportResult([$result], []),
        );
    }

    private function createPatternConfigService(string $pattern = '{n}'): UserNumberRangeConfigService
    {
        $result = static::createStub(Result::class);
        $result->method('fetchAssociative')->willReturn([
            'id' => Uuid::fromHexToBytes('00000000000000000000000000000001'),
            'pattern' => $pattern,
        ]);
        $queryBuilder = static::createStub(QueryBuilder::class);
        foreach (['select', 'from', 'innerJoin', 'where', 'setParameter', 'orderBy', 'addOrderBy', 'leftJoin', 'andWhere'] as $method) {
            $queryBuilder->method($method)->willReturn($queryBuilder);
        }
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection = static::createStub(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        return new UserNumberRangeConfigService($connection);
    }

    /**
     * @param array<string, array{id: string, pattern: string}> $configurations
     */
    private function createPatternConfigServiceForChannels(array $configurations): UserNumberRangeConfigService
    {
        $requestedChannelId = 'global';
        $result = static::createStub(Result::class);
        $result->method('fetchAssociative')->willReturnCallback(
            static function () use (&$requestedChannelId, $configurations): array|false {
                $configuration = $configurations[$requestedChannelId] ?? null;
                if ($configuration === null) {
                    return false;
                }

                return [
                    'id' => Uuid::fromHexToBytes($configuration['id']),
                    'pattern' => $configuration['pattern'],
                ];
            }
        );

        $queryBuilder = static::createStub(QueryBuilder::class);
        foreach (['select', 'from', 'innerJoin', 'where', 'orderBy', 'addOrderBy', 'leftJoin', 'andWhere'] as $method) {
            $queryBuilder->method($method)->willReturn($queryBuilder);
        }
        $queryBuilder->method('setParameter')->willReturnCallback(
            static function (string $name, mixed $value) use (&$requestedChannelId, $queryBuilder): QueryBuilder {
                if ($name === 'channelId') {
                    $requestedChannelId = Uuid::fromBytesToHex($value);
                }

                return $queryBuilder;
            }
        );
        $queryBuilder->method('executeQuery')->willReturn($result);

        $connection = static::createStub(Connection::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilder);

        return new UserNumberRangeConfigService($connection);
    }
}

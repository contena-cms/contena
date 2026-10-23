<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupCollection;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupDefinition;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\Content\Flow\Dispatching\Action\SetUserGroupCustomFieldAction;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Event\UserGroupAware;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(SetUserGroupCustomFieldAction::class)]
class SetUserGroupCustomFieldActionTest extends TestCase
{
    private Connection&Stub $connection;

    /**
     * @var Stub&EntityRepository<UserGroupCollection>
     */
    private Stub&EntityRepository $repository;

    private SetUserGroupCustomFieldAction $action;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->repository = static::createStub(EntityRepository::class);

        $this->action = new SetUserGroupCustomFieldAction($this->connection, $this->repository);
    }

    public function testRequirements(): void
    {
        static::assertSame(
            [UserGroupAware::class],
            $this->action->requirements()
        );
    }

    public function testName(): void
    {
        static::assertSame('action.set.user.group.custom.field', SetUserGroupCustomFieldAction::getName());
    }

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $existsData
     * @param array<string, mixed> $expected
     */
    #[DataProvider('actionExecutedProvider')]
    public function testExecutedAction(array $config, array $existsData, array $expected): void
    {
        $userGroupId = Uuid::randomHex();
        $userGroup = new UserGroupEntity();
        $userGroup->setId($userGroupId);
        $userGroup->setUniqueIdentifier($userGroupId);
        $userGroup->setCustomFields($existsData);

        $context = Context::createDefaultContext();
        $flow = new StorableFlow('', $context, [], [UserGroupAware::CUSTOMER_GROUP_ID => $userGroupId]);
        $flow->setConfig($config);

        $entitySearchResult = new EntitySearchResult(
            UserGroupDefinition::ENTITY_NAME,
            1,
            new UserGroupCollection([$userGroup]),
            null,
            new Criteria(),
            $context
        );

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->willReturn($entitySearchResult);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn('custom_field_test');

        $repository->expects($this->once())
            ->method('update')
            ->with([['id' => $userGroupId, 'customFields' => $expected['custom_field_test'] ? $expected : null]]);

        $this->createAction($connection, $repository)->handleFlow($flow);
    }

    public function testActionWithNotAware(): void
    {
        $flow = new StorableFlow('', Context::createDefaultContext(), [], []);
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('update');

        $this->createAction(repository: $repository)->handleFlow($flow);
    }

    public static function actionExecutedProvider(): \Generator
    {
        yield 'Test aware with upsert config' => [
            [
                'entity' => 'user_group',
                'customFieldId' => Uuid::randomHex(),
                'customFieldText' => 'custom_field_test',
                'customFieldValue' => ['blue', 'gray'],
                'customFieldSetId' => null,
                'customFieldSetText' => null,
                'option' => 'upsert',
            ],
            [
                'custom_field_test' => ['red', 'green'],
            ],
            [
                'custom_field_test' => ['blue', 'gray'],
            ],
        ];

        yield 'Test aware with create config' => [
            [
                'entity' => 'user_group',
                'customFieldId' => Uuid::randomHex(),
                'customFieldText' => null,
                'customFieldValue' => ['blue', 'gray'],
                'customFieldSetId' => null,
                'customFieldSetText' => null,
                'option' => 'create',
            ],
            [
                'test' => ['red', 'green'],
            ],
            [
                'test' => ['red', 'green'],
                'custom_field_test' => ['blue', 'gray'],
            ],
        ];

        yield 'Test aware with clear config' => [
            [
                'entity' => 'user_group',
                'customFieldId' => Uuid::randomHex(),
                'customFieldText' => 'custom_field_test',
                'customFieldValue' => null,
                'customFieldSetId' => null,
                'customFieldSetText' => null,
                'option' => 'clear',
            ],
            [
                'custom_field_test' => ['red', 'green', 'blue'],
            ],
            [
                'custom_field_test' => null,
            ],
        ];

        yield 'Test aware with add config' => [
            [
                'entity' => 'user_group',
                'customFieldId' => Uuid::randomHex(),
                'customFieldText' => 'custom_field_test',
                'customFieldValue' => ['blue', 'gray'],
                'customFieldSetId' => null,
                'customFieldSetText' => null,
                'option' => 'add',
            ],
            [
                'custom_field_test' => ['red', 'green'],
            ],
            [
                'custom_field_test' => ['red', 'green', 'blue', 'gray'],
            ],
        ];

        yield 'Test aware with remove config' => [
            [
                'entity' => 'user_group',
                'customFieldId' => Uuid::randomHex(),
                'customFieldText' => 'custom_field_test',
                'customFieldValue' => ['green', 'blue'],
                'customFieldSetId' => null,
                'customFieldSetText' => null,
                'option' => 'remove',
            ],
            [
                'custom_field_test' => ['red', 'green', 'blue'],
            ],
            [
                'custom_field_test' => ['red'],
            ],
        ];
    }

    /**
     * @param EntityRepository<UserGroupCollection>|null $repository
     */
    private function createAction(?Connection $connection = null, ?EntityRepository $repository = null): SetUserGroupCustomFieldAction
    {
        return new SetUserGroupCustomFieldAction(
            $connection ?? $this->connection,
            $repository ?? $this->repository,
        );
    }
}

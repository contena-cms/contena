<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Flow\Dispatching\Action\AddUserTagAction;
use Contena\Core\Content\Flow\Dispatching\Action\AssignUserStatusAction;
use Contena\Core\Content\Flow\Dispatching\Action\CreateNotificationAction;
use Contena\Core\Content\Flow\Dispatching\Action\CustomFieldActionTrait;
use Contena\Core\Content\Flow\Dispatching\Action\RemoveUserTagAction;
use Contena\Core\Content\Flow\Dispatching\Action\SetUserCustomFieldAction;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Event\UserAware;
use Contena\Core\Framework\Notification\NotificationService;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;

/**
 * @internal
 */
#[CoversClass(AddUserTagAction::class)]
#[CoversClass(AssignUserStatusAction::class)]
#[CoversClass(CreateNotificationAction::class)]
#[CoversClass(CustomFieldActionTrait::class)]
#[CoversClass(RemoveUserTagAction::class)]
#[CoversClass(SetUserCustomFieldAction::class)]
class UserFlowActionsTest extends TestCase
{
    public function testCreateAdministrationNotification(): void
    {
        $context = Context::createDefaultContext();
        $flow = new StorableFlow('user.recovery.request', $context);
        $flow->setConfig([
            'status' => 'warning',
            'message' => '  Reset requested  ',
            'adminOnly' => true,
            'requiredPrivileges' => ['user.viewer', '', 42],
        ]);
        $notificationService = $this->createMock(NotificationService::class);
        $notificationService->expects($this->once())
            ->method('createNotification')
            ->with(
                static::callback(static fn (array $notification): bool => Uuid::isValid($notification['id'])
                    && $notification['status'] === 'warning'
                    && $notification['message'] === 'Reset requested'
                    && $notification['adminOnly'] === true
                    && $notification['requiredPrivileges'] === ['user.viewer']),
                $context,
            );

        new CreateNotificationAction($notificationService)->handleFlow($flow);
    }

    public function testAssignUserStatusUpdatesTheActiveFlag(): void
    {
        $context = Context::createDefaultContext();
        $flow = $this->createUserFlow($context);
        $flow->setConfig(['active' => false]);

        /** @var EntityRepository<UserCollection>&MockObject $userRepository */
        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->expects($this->once())
            ->method('update')
            ->with([['id' => 'user-id', 'active' => false]], $context);

        new AssignUserStatusAction($userRepository)->handleFlow($flow);
    }

    public function testAddAndRemoveUserTags(): void
    {
        $context = Context::createDefaultContext();
        $flow = $this->createUserFlow($context);
        $flow->setConfig(['tagIds' => ['tag-one', 'tag-two']]);

        /** @var EntityRepository<UserCollection>&MockObject $userRepository */
        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->expects($this->once())
            ->method('update')
            ->with([[
                'id' => 'user-id',
                'tags' => [['id' => 'tag-one'], ['id' => 'tag-two']],
            ]], $context);
        new AddUserTagAction($userRepository)->handleFlow($flow);

        /** @var EntityRepository<EntityCollection<Entity>>&MockObject $userTagRepository */
        $userTagRepository = $this->createMock(EntityRepository::class);
        $userTagRepository->expects($this->once())
            ->method('delete')
            ->with([
                ['userId' => 'user-id', 'tagId' => 'tag-one'],
                ['userId' => 'user-id', 'tagId' => 'tag-two'],
            ], $context);
        new RemoveUserTagAction($userTagRepository)->handleFlow($flow);
    }

    public function testSetUserCustomField(): void
    {
        $context = Context::createDefaultContext();
        $flow = $this->createUserFlow($context);
        $customFieldId = Uuid::randomHex();
        $flow->setConfig([
            'customFieldId' => $customFieldId,
            'customFieldValue' => 'gold',
            'option' => 'upsert',
        ]);
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchOne')
            ->willReturn('profile_level');
        $user = new UserEntity();
        $user->setId('user-id');
        $user->setCustomFields(['source' => 'recovery']);

        /** @var EntityRepository<UserCollection>&MockObject $userRepository */
        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(1, new UserCollection([$user]), null, new Criteria(), $context));
        $userRepository->expects($this->once())
            ->method('update')
            ->with([[
                'id' => 'user-id',
                'customFields' => ['source' => 'recovery', 'profile_level' => 'gold'],
            ]], $context);

        new SetUserCustomFieldAction($connection, $userRepository)->handleFlow($flow);
    }

    private function createUserFlow(Context $context): StorableFlow
    {
        return new StorableFlow('user.recovery.request', $context, [], [UserAware::USER_ID => 'user-id']);
    }
}

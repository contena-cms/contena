<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupCollection;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupDefinition;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\System\User\Api\UserGroupRegistrationActionController;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Event\UserGroupRegistrationDeclined;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextRestorer;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(UserGroupRegistrationActionController::class)]
class UserGroupRegistrationActionControllerTest extends TestCase
{
    private UserGroupRegistrationActionController $controllerMock;

    /**
     * @var Stub&EntityRepository<UserCollection>
     */
    private Stub&EntityRepository $userRepositoryMock;

    /**
     * @var Stub&EntityRepository<UserGroupCollection>
     */
    private Stub&EntityRepository $userGroupRepositoryMock;

    private Stub&EventDispatcher $eventDispatcherMock;

    private Stub&ChannelContextRestorer $restorerMock;

    protected function setUp(): void
    {
        $this->userRepositoryMock = static::createStub(EntityRepository::class);
        $this->userGroupRepositoryMock = static::createStub(EntityRepository::class);
        $this->eventDispatcherMock = static::createStub(EventDispatcher::class);
        $this->restorerMock = static::createStub(ChannelContextRestorer::class);

        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getContext')->willReturn(Context::createDefaultContext());
        $this->restorerMock->method('restoreByUser')->willReturn($channelContext);

        $this->controllerMock = new UserGroupRegistrationActionController(
            $this->userRepositoryMock,
            $this->userGroupRepositoryMock,
            $this->eventDispatcherMock,
            $this->restorerMock,
        );
    }

    /**
     * @param UserEntity[] $users
     */
    #[DataProvider('groupRegistrationActionDataProvider')]
    public function testGroupRegistrationAcceptMatches(?int $expectedResCode, ?array $users, Request $request, ?\Exception $expectedException): void
    {
        $context = Context::createDefaultContext();

        if ($users !== null) {
            $userCollection = new UserCollection($users);
            $this->setSearchReturn($context, $userCollection);
            $this->setUserGroupSearchReturn($context, $userCollection);
        }

        if ($expectedException !== null && $expectedResCode === null) {
            $this->expectExceptionObject($expectedException);
        }

        $res = $this->controllerMock->accept($request, $context);
        static::assertSame($expectedResCode, $res->getStatusCode());
    }

    /**
     * @param UserEntity[] $users
     */
    #[DataProvider('groupRegistrationActionDataProvider')]
    public function testGroupRegistrationDeclineMatches(?int $expectedResCode, ?array $users, Request $request, ?\Exception $expectedException): void
    {
        $context = Context::createDefaultContext();

        if ($users !== null) {
            $userCollection = new UserCollection($users);
            $this->setSearchReturn($context, $userCollection);
            $this->setUserGroupSearchReturn($context, $userCollection);
        }

        if ($expectedException !== null && $expectedResCode === null) {
            $this->expectExceptionObject($expectedException);
        }

        $res = $this->controllerMock->decline($request, $context);
        static::assertSame($expectedResCode, $res->getStatusCode());
    }

    /**
     * @return iterable<string, array{int|null, array<UserEntity>|null, Request, \Exception|null}>
     */
    public static function groupRegistrationActionDataProvider(): iterable
    {
        $invalidUser = Uuid::randomHex();
        yield 'without admin_user' => [null, null, self::createRequest([$invalidUser]), UserException::usersNotFound([$invalidUser])];

        $missingUser = self::createUser();
        $missingUserId = $missingUser->getId();
        yield 'without user' => [null, null, self::createRequest([$missingUserId]), UserException::usersNotFound([$missingUserId])];

        yield 'without userId' => [null, null, self::createRequest([]), UserException::userIdsParameterIsMissing()];

        $userWithoutRequest = self::createUser(false);
        $userWithoutRequestId = $userWithoutRequest->getId();
        yield 'without request group' => [null, [$userWithoutRequest], self::createRequest([$userWithoutRequestId]), UserException::groupRequestNotFound($userWithoutRequestId)];

        $acceptUser = self::createUser();
        $acceptUserId = $acceptUser->getId();
        yield 'accept/decline' => [204, [$acceptUser], self::createRequest([$acceptUserId]), null];

        $silentUser = self::createUser(false);
        $silentUserId = $silentUser->getId();
        yield 'accept/decline silent' => [204, [$silentUser], self::createRequest([$silentUserId], true), null];

        $batchUserA = self::createUser();
        $batchUserAId = $batchUserA->getId();
        $batchUserB = self::createUser();
        $batchUserBId = $batchUserB->getId();
        yield 'in batch' => [204, [$batchUserA, $batchUserB], self::createRequest([$batchUserAId, $batchUserBId]), null];
    }

    public function testDeclineUserRequestedGroupIsSetCorrectly(): void
    {
        $context = Context::createDefaultContext();

        $assignedUserGroup = new UserGroupEntity();
        $assignedUserGroup->setId(Uuid::randomHex());

        $requestedUserGroup = new UserGroupEntity();
        $requestedUserGroup->setId(Uuid::randomHex());

        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $user->setLanguageId(Defaults::LANGUAGE_SYSTEM);
        $user->setRequestedGroupId($requestedUserGroup->getId());
        $user->setRequestedGroup($requestedUserGroup);
        $user->setGroupId($assignedUserGroup->getId());

        $request = self::createRequest([$user->getId()]);

        $this->setSearchReturn($context, new UserCollection([$user]));

        $this->userGroupRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                UserGroupDefinition::ENTITY_NAME,
                1,
                new UserGroupCollection([$requestedUserGroup]),
                null,
                new Criteria(),
                $context,
            )
        );

        // test case to ensure the event contains the declined requested user group
        $this->eventDispatcherMock->method('dispatch')->willReturnCallback(static function (UserGroupRegistrationDeclined $userGroupRegistrationDeclined) use ($user, $requestedUserGroup) {
            static::assertSame($user, $userGroupRegistrationDeclined->getUser());
            static::assertSame($requestedUserGroup, $userGroupRegistrationDeclined->getUserGroup());

            return $userGroupRegistrationDeclined;
        });

        $this->controllerMock->decline($request, $context);
    }

    private static function createUser(bool $requestedGroup = true): UserEntity
    {
        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $user->setActive(true);
        $user->setLanguageId(Defaults::LANGUAGE_SYSTEM);

        if ($requestedGroup) {
            $userGroup = new UserGroupEntity();
            $userGroup->setId(Uuid::randomHex());
            $user->setRequestedGroup($userGroup);
            $user->setRequestedGroupId($userGroup->getId());
        }

        return $user;
    }

    /**
     * @param string[] $userId
     */
    private static function createRequest(array $userId, bool $silentError = false): Request
    {
        $request = new Request();
        $request->request->add(['userIds' => $userId, 'silentError' => $silentError]);

        return $request;
    }

    private function setSearchReturn(Context $context, ?UserCollection $collection = null): void
    {
        if (!$collection instanceof UserCollection) {
            $collection = new UserCollection();
        }
        $criteria = new Criteria(array_values($collection->getIds()));

        $this->userRepositoryMock->method('search')
            ->willReturnOnConsecutiveCalls(
                new EntitySearchResult(
                    UserDefinition::ENTITY_NAME,
                    $collection->count(),
                    $collection,
                    null,
                    $criteria,
                    $context
                ),
            );
    }

    private function setUserGroupSearchReturn(Context $context, UserCollection $users): void
    {
        $userGroups = [];
        foreach ($users as $user) {
            $requestedGroupId = $user->getRequestedGroupId();
            if ($requestedGroupId === null || isset($userGroups[$requestedGroupId])) {
                continue;
            }

            $userGroup = new UserGroupEntity();
            $userGroup->setId($requestedGroupId);
            $userGroups[$requestedGroupId] = $userGroup;
        }

        $collection = new UserGroupCollection(\array_values($userGroups));

        $this->userGroupRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                UserGroupDefinition::ENTITY_NAME,
                $collection->count(),
                $collection,
                null,
                new Criteria(),
                $context,
            )
        );
    }
}

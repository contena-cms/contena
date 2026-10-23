<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Api;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\System\User\Api\UserGroupRegistrationActionController;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Event\UserGroupRegistrationAccepted;
use Contena\Core\System\User\Event\UserGroupRegistrationDeclined;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextRestorer;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class UserGroupRegistrationActionControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    public const B2B_CUSTOMER_GROUP_NAME = 'B2B_GROUP';

    public function testAcceptAcceptedUserGroupIsSetCorrectly(): void
    {
        $requestedUserGroup = $this->createUserGroup();
        $userId = $this->createUser(userOverride: ['requestedGroupId' => $requestedUserGroup->getId()]);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        static::assertInstanceOf(TraceableEventDispatcher::class, $eventDispatcher);
        $controller = $this->createController($eventDispatcher);

        $caughtEvent = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            UserGroupRegistrationAccepted::class,
            static function (UserGroupRegistrationAccepted $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            }
        );

        $request = new Request();
        $request->request->add(['userIds' => [$userId]]);

        $controller->accept($request, Context::createDefaultContext());

        static::assertInstanceOf(UserGroupRegistrationAccepted::class, $caughtEvent);
        static::assertSame($userId, $caughtEvent->getUser()->getId());
        static::assertSame($requestedUserGroup->getId(), $caughtEvent->getUserGroup()->getId());
        static::assertSame(self::B2B_CUSTOMER_GROUP_NAME, $caughtEvent->getUserGroup()->getName());

        $userResult = $this->fetchUserById($userId);
        static::assertInstanceOf(UserEntity::class, $userResult);
        static::assertSame($requestedUserGroup->getId(), $userResult->getGroupId());
        static::assertNull($userResult->getRequestedGroupId());
    }

    public function testDeclineDeclinedUserGroupIsSetCorrectly(): void
    {
        $requestedUserGroup = $this->createUserGroup();
        $userId = $this->createUser(userOverride: ['requestedGroupId' => $requestedUserGroup->getId()]);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        static::assertInstanceOf(TraceableEventDispatcher::class, $eventDispatcher);
        $controller = $this->createController($eventDispatcher);

        $caughtEvent = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            UserGroupRegistrationDeclined::class,
            static function (UserGroupRegistrationDeclined $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            }
        );

        $request = new Request();
        $request->request->add(['userIds' => [$userId]]);

        $controller->decline($request, Context::createDefaultContext());

        // Check requested userGroup is set in event
        static::assertInstanceOf(UserGroupRegistrationDeclined::class, $caughtEvent);
        static::assertSame($userId, $caughtEvent->getUser()->getId());
        static::assertSame($requestedUserGroup->getId(), $caughtEvent->getUserGroup()->getId());
        static::assertSame(self::B2B_CUSTOMER_GROUP_NAME, $caughtEvent->getUserGroup()->getName());

        $userResult = $this->fetchUserById($userId);
        static::assertInstanceOf(UserEntity::class, $userResult);
        static::assertNull($userResult->getRequestedGroupId());
    }

    public function testAcceptWithInactiveUser(): void
    {
        $requestedUserGroup = $this->createUserGroup();
        $userId = $this->createUser(userOverride: [
            'requestedGroupId' => $requestedUserGroup->getId(),
            'active' => false,
        ]);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        static::assertInstanceOf(TraceableEventDispatcher::class, $eventDispatcher);
        $controller = $this->createController($eventDispatcher);

        $request = new Request();
        $request->request->add(['userIds' => [$userId]]);

        $controller->accept($request, Context::createDefaultContext());

        $userResult = $this->fetchUserById($userId);
        static::assertInstanceOf(UserEntity::class, $userResult);
        static::assertFalse($userResult->getActive());
        static::assertSame($requestedUserGroup->getId(), $userResult->getGroupId());
        static::assertNull($userResult->getRequestedGroupId());
    }

    public function testAcceptDispatchesUserLanguageContext(): void
    {
        $languageId = $this->createLanguage();
        $requestedUserGroup = $this->createUserGroup();
        $userId = $this->createUser(userOverride: [
            'requestedGroupId' => $requestedUserGroup->getId(),
            'languageId' => $languageId,
        ]);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        static::assertInstanceOf(TraceableEventDispatcher::class, $eventDispatcher);
        $controller = $this->createController($eventDispatcher);

        $caughtEvent = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            UserGroupRegistrationAccepted::class,
            static function (UserGroupRegistrationAccepted $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            }
        );

        $request = new Request();
        $request->request->add(['userIds' => [$userId]]);

        $controller->accept($request, Context::createDefaultContext());

        static::assertInstanceOf(UserGroupRegistrationAccepted::class, $caughtEvent);
        static::assertSame($languageId, $caughtEvent->getContext()->getLanguageId());
    }

    public function testDeclineWithInactiveUser(): void
    {
        $requestedUserGroup = $this->createUserGroup();
        $userId = $this->createUser(userOverride: [
            'requestedGroupId' => $requestedUserGroup->getId(),
            'active' => false,
        ]);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        static::assertInstanceOf(TraceableEventDispatcher::class, $eventDispatcher);
        $controller = $this->createController($eventDispatcher);

        $request = new Request();
        $request->request->add(['userIds' => [$userId]]);

        $controller->decline($request, Context::createDefaultContext());

        $userResult = $this->fetchUserById($userId);
        static::assertInstanceOf(UserEntity::class, $userResult);
        static::assertFalse($userResult->getActive());
        static::assertNull($userResult->getRequestedGroupId());
    }

    public function testDeclineDispatchesUserLanguageContext(): void
    {
        $languageId = $this->createLanguage();
        $requestedUserGroup = $this->createUserGroup();
        $userId = $this->createUser(userOverride: [
            'requestedGroupId' => $requestedUserGroup->getId(),
            'languageId' => $languageId,
        ]);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        static::assertInstanceOf(TraceableEventDispatcher::class, $eventDispatcher);
        $controller = $this->createController($eventDispatcher);

        $caughtEvent = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            UserGroupRegistrationDeclined::class,
            static function (UserGroupRegistrationDeclined $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            }
        );

        $request = new Request();
        $request->request->add(['userIds' => [$userId]]);

        $controller->decline($request, Context::createDefaultContext());

        static::assertInstanceOf(UserGroupRegistrationDeclined::class, $caughtEvent);
        static::assertSame($languageId, $caughtEvent->getContext()->getLanguageId());
    }

    public function testAcceptThrowsExceptionOnUnknownRequestedUserGroup(): void
    {
        $missingUserGroupId = Uuid::randomHex();
        $userId = $this->createUser(userOverride: ['requestedGroupId' => $missingUserGroupId]);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        static::assertInstanceOf(TraceableEventDispatcher::class, $eventDispatcher);
        $controller = $this->createController($eventDispatcher);

        $request = new Request();
        $request->request->add(['userIds' => [$userId]]);

        static::expectExceptionObject(UserException::userGroupNotFound($missingUserGroupId));

        $controller->accept($request, Context::createDefaultContext());
    }

    public function testDeclineThrowsExceptionOnUnknownRequestedUserGroup(): void
    {
        $missingUserGroupId = Uuid::randomHex();
        $userId = $this->createUser(userOverride: ['requestedGroupId' => $missingUserGroupId]);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        static::assertInstanceOf(TraceableEventDispatcher::class, $eventDispatcher);
        $controller = $this->createController($eventDispatcher);

        $request = new Request();
        $request->request->add(['userIds' => [$userId]]);

        static::expectExceptionObject(UserException::userGroupNotFound($missingUserGroupId));

        $controller->decline($request, Context::createDefaultContext());
    }

    private function createUserGroup(): UserGroupEntity
    {
        $userGroup = new UserGroupEntity();
        $userGroup->setId(Uuid::randomHex());
        $userGroup->setName(self::B2B_CUSTOMER_GROUP_NAME);
        $userGroup->setRegistrationActive(true);
        $userGroup->setRegistrationOnlyCompanyRegistration(true);

        $this->getContainer()->get('user_group.repository')
            ->create([$userGroup->jsonSerialize()], Context::createDefaultContext());

        return $userGroup;
    }

    private function createController(TraceableEventDispatcher $eventDispatcher): UserGroupRegistrationActionController
    {
        return new UserGroupRegistrationActionController(
            $this->getContainer()->get('user.repository'),
            $this->getContainer()->get('user_group.repository'),
            $eventDispatcher,
            $this->getContainer()->get(ChannelContextRestorer::class),
        );
    }

    private function fetchUserById(string $userId): ?UserEntity
    {
        $criteria = new Criteria([$userId]);

        $user = $this->getContainer()->get('user.repository')
            ->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        \assert($user === null || $user instanceof UserEntity);

        return $user;
    }

    private function createLanguage(): string
    {
        $languageId = Uuid::randomHex();

        $this->getContainer()->get('language.repository')->create(
            [[
                'id' => $languageId,
                'name' => \sprintf('test-language-%s', $languageId),
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
                'parentId' => Defaults::LANGUAGE_SYSTEM,
                'active' => true,
                'channels' => [
                    ['id' => TestDefaults::CHANNEL],
                ],
                'channelDefaultAssignments' => [
                    ['id' => TestDefaults::CHANNEL],
                ],
            ]],
            Context::createDefaultContext()
        );

        return $languageId;
    }
}

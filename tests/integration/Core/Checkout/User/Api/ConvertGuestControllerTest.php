<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Api\ConvertGuestController;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Event\UserAccountRecoverRequestEvent;
use Contena\Core\System\User\Channel\ConvertGuestRoute;
use Contena\Core\System\User\Channel\SendPasswordRecoveryMailRoute;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ConvertGuestControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private ConvertGuestController $controller;

    /**
     * @var EntityRepository<UserCollection>
     */
    private readonly EntityRepository $userRepository;

    protected function setUp(): void
    {
        $this->userRepository = $this->getContainer()->get('user.repository');

        $this->controller = new ConvertGuestController(
            $this->userRepository,
            $this->getContainer()->get(ChannelContextService::class),
            $this->getContainer()->get(ConvertGuestRoute::class),
            $this->getContainer()->get(SendPasswordRecoveryMailRoute::class),
            $this->getContainer()->get(Connection::class),
        );
    }

    public function testCannotConvertNotExisingUser(): void
    {
        $userId = Uuid::randomHex();
        $request = new Request();

        static::expectException(UserException::class);

        $this->controller->convert($request, Context::createDefaultContext(), $userId);
    }

    public function testCannotConvertARegisteredUser(): void
    {
        $userId = $this->createUser();
        $request = new Request();

        static::expectException(UserException::class);

        $this->controller->convert($request, Context::createDefaultContext(), $userId);
    }

    public function testCannotSendRecoveryWithoutDomainUrl(): void
    {
        $context = Context::createDefaultContext();
        $request = new Request();

        $userId = $this->createUser('test@test.com', guest: true);

        $user = $this->userRepository
            ->search(new Criteria([$userId]), $context)->getEntities()
            ->first();

        static::assertInstanceOf(UserEntity::class, $user);

        $this->removeChannelDomains($user->getChannelId(), $context);

        static::expectException(UserException::class);

        $this->controller->convert($request, $context, $userId);
    }

    public function testConvertGuestWithPassword(): void
    {
        $request = new Request();
        $request->request->add(['password' => 'password']);

        $userId = $this->createUser(guest: true);

        $caughtEvent = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            UserAccountRecoverRequestEvent::EVENT_NAME,
            function (UserAccountRecoverRequestEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            }
        );

        $this->controller->convert($request, Context::createDefaultContext(), $userId);

        $user = $this->userRepository->search(new Criteria([$userId]), Context::createDefaultContext())->getEntities()->first();

        static::assertNull($caughtEvent);
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertFalse($user->getGuest());
        static::assertNotNull($user->getPassword());
    }

    public function testConvertGuestWithoutPassword(): void
    {
        $request = new Request();
        $userId = $this->createUser('test@test.com', guest: true);

        $caughtEvent = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            UserAccountRecoverRequestEvent::EVENT_NAME,
            function (UserAccountRecoverRequestEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            }
        );

        $this->controller->convert($request, Context::createDefaultContext(), $userId);

        $user = $this->userRepository->search(new Criteria([$userId]), Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(UserAccountRecoverRequestEvent::class, $caughtEvent);
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertFalse($user->getGuest());
        static::assertNotNull($user->getPassword());
    }

    private function removeChannelDomains(string $channelId, Context $context): void
    {
        $repository = $this->getContainer()->get('channel_domain.repository');

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('channelId', $channelId));

        $ids = $repository->searchIds($criteria, $context)->getPrimaryKeyData();
        if ($ids === []) {
            return;
        }

        $repository->delete($ids, $context);
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User;

use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\Event\UserBeforeLoginEvent;
use Contena\Core\System\User\Event\UserLoginEvent;
use Contena\Core\System\User\Event\UserLogoutEvent;
use Contena\Core\System\User\Exception\BadCredentialsException;
use Contena\Core\System\User\Channel\AbstractLoginRoute;
use Contena\Core\System\User\Channel\AccountService;
use Contena\Core\System\User\Channel\LoginRoute;
use Contena\Core\System\User\Channel\LogoutRoute;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class AccountServiceEventTest extends TestCase
{
    use ChannelFunctionalTestBehaviour;

    private AccountService $accountService;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    private ChannelContext $channelContext;

    private AbstractLoginRoute $loginRoute;

    private LogoutRoute $logoutRoute;

    private ?string $caughtEmail = null;

    /**
     * @var \Closure(UserBeforeLoginEvent): void
     */
    private \Closure $emailListenerClosure;

    /**
     * @var \Closure(UserLoginEvent|UserLogoutEvent): void
     */
    private \Closure $userListenerClosure;

    protected function setUp(): void
    {
        $this->accountService = static::getContainer()->get(AccountService::class);
        $this->userRepository = static::getContainer()->get('user.repository');
        $this->logoutRoute = static::getContainer()->get(LogoutRoute::class);
        $this->loginRoute = static::getContainer()->get(LoginRoute::class);

        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $this->channelContext = $channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $this->createUser('info@example.com');

        $this->emailListenerClosure = function (UserBeforeLoginEvent $event): void {
            $this->caughtEmail = $event->getEmail();
        };

        $this->userListenerClosure = function (UserLoginEvent|UserLogoutEvent $event): void {
            $this->caughtEmail = $event->getUser()->getEmail();
        };
    }

    public function testLoginBeforeEventNotDispatchedIfNoCredentialsGivenViaLoginRoute(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(UserBeforeLoginEvent::class, $this->emailListenerClosure);

        $dataBag = new DataBag();
        $dataBag->add([
            'username' => '',
            'password' => 'contena',
        ]);

        $this->expectExceptionObject(new BadCredentialsException());

        try {
            $this->loginRoute->login($dataBag->toRequestDataBag(), $this->channelContext);
        } finally {
            static::assertNull($this->caughtEmail, 'Event "' . UserBeforeLoginEvent::class . '" did run');
        }
    }

    public function testLoginBeforeEventNotDispatchedIfNoCredentialsGivenViaAccountService(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(UserBeforeLoginEvent::class, $this->emailListenerClosure);

        $this->expectExceptionObject(new BadCredentialsException());

        try {
            $this->accountService->loginByCredentials('', 'contena', $this->channelContext);
        } finally {
            static::assertNull($this->caughtEmail, 'Event "' . UserBeforeLoginEvent::class . '" did run');
        }
    }

    public function testUserBeforeLoginEventDispatchedViaLoginRoute(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(UserBeforeLoginEvent::class, $this->emailListenerClosure);

        $dataBag = new DataBag();
        $dataBag->add([
            'username' => 'info@example.com',
            'password' => 'contena',
        ]);

        $this->loginRoute->login($dataBag->toRequestDataBag(), $this->channelContext);
        static::assertSame('info@example.com', $this->caughtEmail, 'Event "' . UserBeforeLoginEvent::class . '" did not run');
    }

    public function testUserBeforeLoginEventDispatchedViaAccountService(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(UserBeforeLoginEvent::class, $this->emailListenerClosure);

        $this->accountService->loginByCredentials('info@example.com', 'contena', $this->channelContext);
        static::assertSame('info@example.com', $this->caughtEmail, 'Event "' . UserBeforeLoginEvent::class . '" did not run');
    }

    public function testUserLoginEventDispatchedViaLoginRoute(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(UserLoginEvent::class, $this->userListenerClosure);

        $dataBag = new DataBag();
        $dataBag->add([
            'username' => 'info@example.com',
            'password' => 'contena',
        ]);

        $this->loginRoute->login($dataBag->toRequestDataBag(), $this->channelContext);
        static::assertSame('info@example.com', $this->caughtEmail, 'Event "' . UserLoginEvent::class . '" did not run');
    }

    public function testUserLoginEventDispatchedViaAccountService(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(UserLoginEvent::class, $this->userListenerClosure);

        $this->accountService->loginByCredentials('info@example.com', 'contena', $this->channelContext);
        static::assertSame('info@example.com', $this->caughtEmail, 'Event "' . UserLoginEvent::class . '" did not run');
    }

    public function testLogoutEventsDispatched(): void
    {
        $email = 'info@example.com';

        EventHookDispatcher::fromContainer(static::getContainer())->on(UserLogoutEvent::class, $this->userListenerClosure);

        $user = $this->userRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('email', $email)),
            $this->channelContext->getContext()
        )->getEntities()->first();

        $this->channelContext->assign(['user' => $user]);

        static::assertNotNull($user = $this->channelContext->getUser());
        static::assertSame($email, $user->getEmail());

        $this->logoutRoute->logout($this->channelContext, new RequestDataBag());

        static::assertSame('info@example.com', $this->caughtEmail, 'Event "' . UserLogoutEvent::class . '" did not run');
    }
}

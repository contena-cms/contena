<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Customer;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\CustomerCollection;
use Contena\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Contena\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Contena\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Contena\Core\Checkout\Customer\Exception\BadCredentialsException;
use Contena\Core\Checkout\Customer\SalesChannel\AbstractLoginRoute;
use Contena\Core\Checkout\Customer\SalesChannel\AccountService;
use Contena\Core\Checkout\Customer\SalesChannel\LoginRoute;
use Contena\Core\Checkout\Customer\SalesChannel\LogoutRoute;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class AccountServiceEventTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    private AccountService $accountService;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    private SalesChannelContext $salesChannelContext;

    private AbstractLoginRoute $loginRoute;

    private LogoutRoute $logoutRoute;

    private ?string $caughtEmail = null;

    /**
     * @var \Closure(CustomerBeforeLoginEvent): void
     */
    private \Closure $emailListenerClosure;

    /**
     * @var \Closure(CustomerLoginEvent|CustomerLogoutEvent): void
     */
    private \Closure $customerListenerClosure;

    protected function setUp(): void
    {
        $this->accountService = static::getContainer()->get(AccountService::class);
        $this->customerRepository = static::getContainer()->get('customer.repository');
        $this->logoutRoute = static::getContainer()->get(LogoutRoute::class);
        $this->loginRoute = static::getContainer()->get(LoginRoute::class);

        $salesChannelContextFactory = static::getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $this->createCustomer('info@example.com');

        $this->emailListenerClosure = function (CustomerBeforeLoginEvent $event): void {
            $this->caughtEmail = $event->getEmail();
        };

        $this->customerListenerClosure = function (CustomerLoginEvent|CustomerLogoutEvent $event): void {
            $this->caughtEmail = $event->getCustomer()->getEmail();
        };
    }

    public function testLoginBeforeEventNotDispatchedIfNoCredentialsGivenViaLoginRoute(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(CustomerBeforeLoginEvent::class, $this->emailListenerClosure);

        $dataBag = new DataBag();
        $dataBag->add([
            'username' => '',
            'password' => 'contena',
        ]);

        $this->expectExceptionObject(new BadCredentialsException());

        try {
            $this->loginRoute->login($dataBag->toRequestDataBag(), $this->salesChannelContext);
        } finally {
            static::assertNull($this->caughtEmail, 'Event "' . CustomerBeforeLoginEvent::class . '" did run');
        }
    }

    public function testLoginBeforeEventNotDispatchedIfNoCredentialsGivenViaAccountService(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(CustomerBeforeLoginEvent::class, $this->emailListenerClosure);

        $this->expectExceptionObject(new BadCredentialsException());

        try {
            $this->accountService->loginByCredentials('', 'contena', $this->salesChannelContext);
        } finally {
            static::assertNull($this->caughtEmail, 'Event "' . CustomerBeforeLoginEvent::class . '" did run');
        }
    }

    public function testCustomerBeforeLoginEventDispatchedViaLoginRoute(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(CustomerBeforeLoginEvent::class, $this->emailListenerClosure);

        $dataBag = new DataBag();
        $dataBag->add([
            'username' => 'info@example.com',
            'password' => 'contena',
        ]);

        $this->loginRoute->login($dataBag->toRequestDataBag(), $this->salesChannelContext);
        static::assertSame('info@example.com', $this->caughtEmail, 'Event "' . CustomerBeforeLoginEvent::class . '" did not run');
    }

    public function testCustomerBeforeLoginEventDispatchedViaAccountService(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(CustomerBeforeLoginEvent::class, $this->emailListenerClosure);

        $this->accountService->loginByCredentials('info@example.com', 'contena', $this->salesChannelContext);
        static::assertSame('info@example.com', $this->caughtEmail, 'Event "' . CustomerBeforeLoginEvent::class . '" did not run');
    }

    public function testCustomerLoginEventDispatchedViaLoginRoute(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(CustomerLoginEvent::class, $this->customerListenerClosure);

        $dataBag = new DataBag();
        $dataBag->add([
            'username' => 'info@example.com',
            'password' => 'contena',
        ]);

        $this->loginRoute->login($dataBag->toRequestDataBag(), $this->salesChannelContext);
        static::assertSame('info@example.com', $this->caughtEmail, 'Event "' . CustomerLoginEvent::class . '" did not run');
    }

    public function testCustomerLoginEventDispatchedViaAccountService(): void
    {
        EventHookDispatcher::fromContainer(static::getContainer())->on(CustomerLoginEvent::class, $this->customerListenerClosure);

        $this->accountService->loginByCredentials('info@example.com', 'contena', $this->salesChannelContext);
        static::assertSame('info@example.com', $this->caughtEmail, 'Event "' . CustomerLoginEvent::class . '" did not run');
    }

    public function testLogoutEventsDispatched(): void
    {
        $email = 'info@example.com';

        EventHookDispatcher::fromContainer(static::getContainer())->on(CustomerLogoutEvent::class, $this->customerListenerClosure);

        $customer = $this->customerRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('email', $email)),
            $this->salesChannelContext->getContext()
        )->getEntities()->first();

        $this->salesChannelContext->assign(['customer' => $customer]);

        static::assertNotNull($customer = $this->salesChannelContext->getCustomer());
        static::assertSame($email, $customer->getEmail());

        $this->logoutRoute->logout($this->salesChannelContext, new RequestDataBag());

        static::assertSame('info@example.com', $this->caughtEmail, 'Event "' . CustomerLogoutEvent::class . '" did not run');
    }
}

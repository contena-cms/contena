<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Customer\Extension\ImitateCustomerRouteExtension;
use Contena\Core\Checkout\Customer\ImitateCustomerTokenGenerator;
use Contena\Core\Checkout\Customer\SalesChannel\AccountService;
use Contena\Core\Checkout\Customer\SalesChannel\ImitateCustomerRoute;
use Contena\Core\Checkout\Customer\SalesChannel\LogoutRoute;
use Contena\Core\Checkout\Customer\Struct\ImitateCustomerToken;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Contena\Core\System\SalesChannel\ContextTokenResponse;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Generator;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ImitateCustomerRoute::class)]
class ImitateCustomerRouteTest extends TestCase
{
    /**
     * @deprecated tag:v6.8.0 - will be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testImitateCustomerOld(): void
    {
        $customerId = Uuid::randomHex();
        $userId = Uuid::randomHex();
        $token = 'testToken';

        $imitateCustomerTokenGenerator = $this->createMock(ImitateCustomerTokenGenerator::class);
        $imitateCustomerTokenGenerator
            ->expects($this->once())
            ->method('validate')
            ->with($token, TestDefaults::SALES_CHANNEL, $customerId, $userId);

        $accountService = static::createStub(AccountService::class);
        $accountService->method('loginById')->willReturn('newToken');

        $route = new ImitateCustomerRoute(
            $accountService,
            $imitateCustomerTokenGenerator,
            static::createStub(LogoutRoute::class),
            static::createStub(SalesChannelContextFactory::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

        $dataBag = new RequestDataBag([
            ImitateCustomerRoute::TOKEN => $token,
            ImitateCustomerRoute::CUSTOMER_ID => $customerId,
            ImitateCustomerRoute::USER_ID => $userId,
        ]);

        $response = $route->imitateCustomerLogin($dataBag, $salesChannelContext);

        static::assertSame('newToken', $response->getToken());
    }

    public function testImitateCustomer(): void
    {
        $token = 'testToken';
        $tokenStruct = new ImitateCustomerToken();
        $tokenStruct->customerId = Uuid::randomHex();
        $tokenStruct->iss = Uuid::randomHex();
        $tokenStruct->salesChannelId = TestDefaults::SALES_CHANNEL;
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->assign(['customer' => null]);

        $imitateCustomerTokenGenerator = $this->createMock(ImitateCustomerTokenGenerator::class);
        $imitateCustomerTokenGenerator
            ->expects($this->once())
            ->method('decode')
            ->with($token)
            ->willReturn($tokenStruct);

        $accountService = $this->createMock(AccountService::class);
        $accountService
            ->expects($this->once())
            ->method('loginById')
            ->with($tokenStruct->customerId, $salesChannelContext)
            ->willReturn('newToken');

        $route = new ImitateCustomerRoute(
            $accountService,
            $imitateCustomerTokenGenerator,
            static::createStub(LogoutRoute::class),
            static::createStub(SalesChannelContextFactory::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $dataBag = new RequestDataBag([
            ImitateCustomerRoute::TOKEN => $token,
        ]);

        $response = $route->imitateCustomerLogin($dataBag, $salesChannelContext);

        static::assertSame('newToken', $response->getToken());
    }

    public function testImitateCustomerWithLoggedInUser(): void
    {
        $token = 'testToken';
        $tokenStruct = new ImitateCustomerToken();
        $tokenStruct->customerId = Uuid::randomHex();
        $tokenStruct->iss = Uuid::randomHex();
        $tokenStruct->salesChannelId = TestDefaults::SALES_CHANNEL;
        $salesChannelContext = Generator::generateSalesChannelContext();

        $imitateCustomerTokenGenerator = $this->createMock(ImitateCustomerTokenGenerator::class);
        $imitateCustomerTokenGenerator
            ->expects($this->once())
            ->method('decode')
            ->with($token)
            ->willReturn($tokenStruct);

        $salesChannelContextFactory = $this->createMock(SalesChannelContextFactory::class);
        $salesChannelContextFactory
            ->expects($this->once())
            ->method('create')
            ->with('loggedOutToken', TestDefaults::SALES_CHANNEL)
            ->willReturn($salesChannelContext);

        $accountService = $this->createMock(AccountService::class);
        $accountService
            ->expects($this->once())
            ->method('loginById')
            ->with($tokenStruct->customerId, $salesChannelContext)
            ->willReturn('newToken');

        $logoutRoute = $this->createMock(LogoutRoute::class);
        $logoutRoute
            ->expects($this->once())
            ->method('logout')
            ->willReturn(new ContextTokenResponse('loggedOutToken'));

        $route = new ImitateCustomerRoute(
            $accountService,
            $imitateCustomerTokenGenerator,
            $logoutRoute,
            $salesChannelContextFactory,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $dataBag = new RequestDataBag([
            ImitateCustomerRoute::TOKEN => $token,
        ]);

        $response = $route->imitateCustomerLogin($dataBag, $salesChannelContext);

        static::assertSame('newToken', $response->getToken());
    }

    public function testPublishesExtension(): void
    {
        $data = new RequestDataBag();
        $context = Generator::generateSalesChannelContext();
        $response = new ContextTokenResponse('token');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('imitate-customer-route.imitate-customer-login.pre', static function (ImitateCustomerRouteExtension $extension) use ($data, $context, $response): void {
            static::assertSame(['data' => $data, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ImitateCustomerRoute(
            static::createStub(AccountService::class),
            static::createStub(ImitateCustomerTokenGenerator::class),
            static::createStub(LogoutRoute::class),
            static::createStub(SalesChannelContextFactory::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->imitateCustomerLogin($data, $context));
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Uuid\Exception\InvalidUuidException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\Channel\Channel\AbstractContextSwitchRoute;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressCollection;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\Channel\AbstractDeleteAddressRoute;
use Contena\Core\System\User\Channel\AbstractListAddressRoute;
use Contena\Core\System\User\Channel\AbstractUpsertAddressRoute;
use Contena\Core\System\User\Channel\AccountService;
use Contena\Core\System\User\Channel\ListAddressRouteResponse;
use Contena\Core\System\User\Exception\AddressNotFoundException;
use Contena\Core\System\User\Exception\CannotDeleteDefaultAddressException;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Generator;
use Contena\Frontend\Controller\AddressController;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[CoversClass(AddressController::class)]
class AddressControllerTest extends TestCase
{

    private Stub&AccountService $accountService;

    private Stub&AbstractListAddressRoute $listAddressRoute;

    private Stub&AbstractUpsertAddressRoute $abstractUpsertAddressRoute;

    private Stub&AbstractDeleteAddressRoute $deleteAddressRoute;

    private Stub&AbstractContextSwitchRoute $contextSwitchRoute;

    private Stub&ChannelContextService $channelContextService;

    protected function setUp(): void
    {
        $this->accountService = static::createStub(AccountService::class);
        $this->listAddressRoute = static::createStub(AbstractListAddressRoute::class);
        $this->abstractUpsertAddressRoute = static::createStub(AbstractUpsertAddressRoute::class);
        $this->deleteAddressRoute = static::createStub(AbstractDeleteAddressRoute::class);
        $this->contextSwitchRoute = static::createStub(AbstractContextSwitchRoute::class);
        $this->channelContextService = static::createStub(ChannelContextService::class);

        $this->controller = $this->buildController();
    }

    public function testAccountAddressOverview(): void
    {
        $user = new UserEntity();
        $user->setId(Uuid::randomHex());

        $response = $this->controller
            ->accountAddressOverview(new Request(), Generator::generateChannelContext(), $user);

        static::assertSame(
            '@Frontend/frontend/page/account/addressbook/index.html.twig',
            $this->controller->recorder()->renderFrontendView
        );
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testAccountCreateAddress(): void
    {
        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $dataBag = new RequestDataBag();
        $dataBag->set('address', new DataBag(['id' => Uuid::randomHex()]));

        $this->controller->accountCreateAddress(new Request(), $dataBag, Generator::generateChannelContext(), $user);

        $renderParams = $this->controller->recorder()->renderFrontendParameters;

        static::assertArrayHasKey('page', $renderParams);
        static::assertArrayHasKey('data', $renderParams);
    }

    public function testAccountEditAddress(): void
    {
        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $request = new Request();
        $request->query->set('redirectTo', 'foo');

        $response = $this->controller->accountEditAddress($request, Generator::generateChannelContext(), $user);
        $renderParams = $this->controller->recorder()->renderFrontendParameters;

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertArrayHasKey('page', $renderParams);
        static::assertSame('foo', $renderParams['redirectTo'] ?? null);
    }

    public function testSwitchDefaultAddressThrowsException(): void
    {
        $dataBag = new RequestDataBag();
        $dataBag->set('type', 'dummy-type');

        $user = new UserEntity();
        $user->setId(Uuid::randomHex());

        $this->expectException(RoutingException::class);

        $this->controller->checkoutSwitchDefaultAddress(new Request(), $dataBag, Generator::generateChannelContext(), $user);
    }

    public function testCheckoutSwitchDefaultShippingAddress(): void
    {
        $context = Generator::generateChannelContext();

        $dataBag = new RequestDataBag();
        $dataBag->set('type', 'shipping');
        $dataBag->set('id', Uuid::randomHex());

        $user = new UserEntity();
        $user->setId(Uuid::randomHex());

        $accountService = $this->createMock(AccountService::class);
        $accountService
            ->expects($this->once())
            ->method('setDefaultShippingAddress');

        $accountService
            ->expects($this->never())
            ->method('setDefaultBillingAddress');

        $contextSwitchRoute = $this->createMock(AbstractContextSwitchRoute::class);
        $contextSwitchRoute
            ->expects($this->once())
            ->method('switchContext');

        $channelContextService = $this->createMock(ChannelContextService::class);
        $channelContextService
            ->expects($this->once())
            ->method('get');

        $controller = $this->buildController(
            accountService: $accountService,
            contextSwitchRoute: $contextSwitchRoute,
            channelContextService: $channelContextService,
        );

        $response = $controller->checkoutSwitchDefaultAddress(new Request(), $dataBag, $context, $user);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.account.addressmanager.get', $response->getTargetUrl());
    }

    public function testCheckoutSwitchDefaultBillingAddress(): void
    {
        $context = Generator::generateChannelContext();

        $dataBag = new RequestDataBag();
        $dataBag->set('type', 'billing');
        $dataBag->set('id', Uuid::randomHex());

        $user = new UserEntity();
        $user->setId(Uuid::randomHex());

        $accountService = $this->createMock(AccountService::class);
        $accountService
            ->expects($this->once())
            ->method('setDefaultBillingAddress');

        $accountService
            ->expects($this->never())
            ->method('setDefaultShippingAddress');

        $contextSwitchRoute = $this->createMock(AbstractContextSwitchRoute::class);
        $contextSwitchRoute
            ->expects($this->once())
            ->method('switchContext');

        $channelContextService = $this->createMock(ChannelContextService::class);
        $channelContextService
            ->expects($this->once())
            ->method('get');

        $controller = $this->buildController(
            accountService: $accountService,
            contextSwitchRoute: $contextSwitchRoute,
            channelContextService: $channelContextService,
        );

        $response = $controller->checkoutSwitchDefaultAddress(new Request(), $dataBag, $context, $user);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.account.addressmanager.get', $response->getTargetUrl());
    }

    public function testAddressManagerSwitchShippingDataBag(): void
    {
        $id = Uuid::randomHex();
        $context = Generator::generateChannelContext();

        $request = new Request();
        $request->request->set(ChannelContextService::SHIPPING_ADDRESS_ID, $id);

        $contextSwitchRoute = $this->createMock(AbstractContextSwitchRoute::class);
        $contextSwitchRoute
            ->expects($this->once())
            ->method('switchContext')
            ->with(
                static::callback(static function ($arg) use ($id) {
                    static::assertInstanceOf(RequestDataBag::class, $arg);
                    static::assertFalse($arg->has(ChannelContextService::BILLING_ADDRESS_ID));
                    static::assertSame($id, $arg->get(ChannelContextService::SHIPPING_ADDRESS_ID));

                    return true;
                }),
                $context
            );

        $controller = $this->buildController(contextSwitchRoute: $contextSwitchRoute);

        $controller->addressManagerSwitch($request, $context);
    }

    public function testSwitchDefaultShippingAddress(): void
    {
        $user = new UserEntity();
        $user->setId(Uuid::randomHex());

        $accountService = $this->createMock(AccountService::class);
        $accountService
            ->expects($this->once())
            ->method('setDefaultBillingAddress');

        $accountService
            ->expects($this->never())
            ->method('setDefaultShippingAddress');

        $controller = $this->buildController(accountService: $accountService);

        $controller->switchDefaultAddress('billing', Uuid::randomHex(), Generator::generateChannelContext(), $user);
    }

    public function testAddressManagerSwitchBillingDataBag(): void
    {
        $id = Uuid::randomHex();
        $context = Generator::generateChannelContext();

        $request = new Request();
        $request->request->set(ChannelContextService::BILLING_ADDRESS_ID, $id);

        $contextSwitchRoute = $this->createMock(AbstractContextSwitchRoute::class);
        $contextSwitchRoute
            ->expects($this->once())
            ->method('switchContext')
            ->with(
                static::callback(static function ($arg) use ($id) {
                    static::assertInstanceOf(RequestDataBag::class, $arg);
                    static::assertFalse($arg->has(ChannelContextService::SHIPPING_ADDRESS_ID));
                    static::assertSame($id, $arg->get(ChannelContextService::BILLING_ADDRESS_ID));

                    return true;
                }),
                $context
            );

        $controller = $this->buildController(contextSwitchRoute: $contextSwitchRoute);

        $controller->addressManagerSwitch($request, $context);
    }

    public function testSwitchDefaultAddressWithInvalidIdThrowsException(): void
    {
        $context = Generator::generateChannelContext();

        $user = new UserEntity();
        $user->setId(Uuid::randomHex());

        $this->expectException(InvalidUuidException::class);

        $this->controller->switchDefaultAddress('shipping', 'foo', $context, $user);
    }

    public function testSwitchDefaultBillingAddress(): void
    {
        $user = new UserEntity();
        $user->setId(Uuid::randomHex());

        $accountService = $this->createMock(AccountService::class);
        $accountService
            ->expects($this->once())
            ->method('setDefaultShippingAddress');

        $accountService
            ->expects($this->never())
            ->method('setDefaultBillingAddress');

        $controller = $this->buildController(accountService: $accountService);

        $controller->switchDefaultAddress('shipping', Uuid::randomHex(), Generator::generateChannelContext(), $user);
    }

    public function testSwitchDefaultBillingAddressWithInvalidId(): void
    {
        $user = new UserEntity();
        $user->setId(Uuid::randomHex());

        $addressId = Uuid::randomHex();

        $this->accountService
            ->method('setDefaultShippingAddress')
            ->willThrowException(new AddressNotFoundException($addressId));

        $this->controller->switchDefaultAddress('shipping', $addressId, Generator::generateChannelContext(), $user);

        static::assertSame(
            ['danger' => ['account.addressDefaultNotChanged']],
            $this->controller->recorder()->flashBag
        );
    }

    public function testSwitchDefaultBillingAddressWithInvalidTye(): void
    {
        $user = new UserEntity();
        $user->setId(Uuid::randomHex());

        $addressId = Uuid::randomHex();

        $this->accountService
            ->method('setDefaultShippingAddress')
            ->willThrowException(new AddressNotFoundException($addressId));

        $this->controller->switchDefaultAddress('foo', $addressId, Generator::generateChannelContext(), $user);

        static::assertSame(
            ['danger' => ['account.addressDefaultNotChanged']],
            $this->controller->recorder()->flashBag
        );
    }

    public function testSaveAddress(): void
    {
        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $user->setGuest(false);

        $dataBag = new RequestDataBag();
        $dataBag->set('address', new DataBag(['id' => Uuid::randomHex()]));

        $response = $this->controller->saveAddress($dataBag, Generator::generateChannelContext(), $user, new Request());
        static::assertInstanceOf(RedirectResponse::class, $response);

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.account.address.page', $response->getTargetUrl());
    }

    public function testSaveAddressWithId(): void
    {
        $user = new UserEntity();
        $user->setId(Uuid::randomHex());

        $dataBag = new RequestDataBag();
        $dataBag->set('address', new DataBag(['id' => Uuid::randomHex()]));

        $abstractUpsertAddressRoute = $this->createMock(AbstractUpsertAddressRoute::class);
        $abstractUpsertAddressRoute
            ->expects($this->once())
            ->method('upsert')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));

        $controller = $this->buildController(abstractUpsertAddressRoute: $abstractUpsertAddressRoute);

        $response = $controller->saveAddress($dataBag, Generator::generateChannelContext(), $user, new Request());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('forward to frontend.account.address.edit.page', $response->getContent());
    }

    public function testSaveAddressWithoutId(): void
    {
        $user = new UserEntity();
        $user->setId(Uuid::randomHex());

        $dataBag = new RequestDataBag();
        $dataBag->set('address', new DataBag(['foo' => 'foo']));

        $abstractUpsertAddressRoute = $this->createMock(AbstractUpsertAddressRoute::class);
        $abstractUpsertAddressRoute
            ->expects($this->once())
            ->method('upsert')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));

        $controller = $this->buildController(abstractUpsertAddressRoute: $abstractUpsertAddressRoute);

        $response = $controller->saveAddress($dataBag, Generator::generateChannelContext(), $user, new Request());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('forward to frontend.account.address.create.page', $response->getContent());
    }

    public function testDeleteAddressWithNoIdThrowsException(): void
    {
        $user = new UserEntity();
        $user->setId(Uuid::randomHex());

        $this->expectException(RoutingException::class);

        $this->controller->deleteAddress('', new Request(), Generator::generateChannelContext(), $user);
    }

    public function testDeleteAddress(): void
    {
        $user = new UserEntity();
        $user->setId(Uuid::randomHex());

        $deleteAddressRoute = $this->createMock(AbstractDeleteAddressRoute::class);
        $deleteAddressRoute
            ->expects($this->once())
            ->method('delete');

        $controller = $this->buildController(deleteAddressRoute: $deleteAddressRoute);

        $response = $controller->deleteAddress(Uuid::randomHex(), new Request(), Generator::generateChannelContext(), $user);
        static::assertInstanceOf(RedirectResponse::class, $response);

        static::assertSame(
            ['success' => ['account.addressDeleted']],
            $controller->recorder()->flashBag
        );

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.account.address.page', $response->getTargetUrl());
    }

    public function testDeleteAddressWithInvalidIdThrowsException(): void
    {
        $addressId = Uuid::randomHex();

        $user = new UserEntity();
        $user->setId(Uuid::randomHex());

        $deleteAddressRoute = $this->createMock(AbstractDeleteAddressRoute::class);
        $deleteAddressRoute
            ->expects($this->once())
            ->method('delete')
            ->willThrowException(new CannotDeleteDefaultAddressException($addressId));

        $controller = $this->buildController(deleteAddressRoute: $deleteAddressRoute);

        $response = $controller->deleteAddress($addressId, new Request(), Generator::generateChannelContext(), $user);
        static::assertInstanceOf(RedirectResponse::class, $response);

        static::assertSame(
            ['danger' => ['account.addressNotDeleted']],
            $controller->recorder()->flashBag
        );

        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.account.address.page', $response->getTargetUrl());
    }

    public function testAddressManager(): void
    {
        $request = new Request();

        $user = new UserEntity();
        $user->setId(Uuid::randomHex());

        $response = $this->controller->addressManager($request, Generator::generateChannelContext(), $user);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame(
            '@Frontend/frontend/component/address/address-manager-modal.html.twig',
            $this->controller->recorder()->renderFrontendView
        );
    }

    public function testAddressManagerWithoutType(): void
    {
        $user = new UserEntity();
        $user->setId(Uuid::randomHex());

        $this->expectException(RoutingException::class);

        $this->controller->addressManagerUpsert(new Request(), new RequestDataBag(), Generator::generateChannelContext(), $user, Uuid::randomHex());
    }

    public function testAddressManagerWithShipping(): void
    {
        $addressId = Uuid::randomHex();

        $dataBag = new RequestDataBag([
            'address' => [
                'id' => $addressId,
            ],
        ]);

        $user = new UserEntity();
        $user->setId(Uuid::randomHex());

        $userAddress = new UserAddressEntity();
        $userAddress->setUniqueIdentifier($addressId);
        $userAddressCollection = new UserAddressCollection([$userAddress]);
        $listAddressRouteResponse = $this->createMock(ListAddressRouteResponse::class);

        $listAddressRoute = $this->createMock(AbstractListAddressRoute::class);
        $listAddressRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($listAddressRouteResponse);

        $listAddressRouteResponse
            ->expects($this->once())
            ->method('getAddressCollection')
            ->willReturn($userAddressCollection);

        $controller = $this->buildController(listAddressRoute: $listAddressRoute);

        $response = $controller->addressManagerUpsert(new Request(), $dataBag, Generator::generateChannelContext(), $user, $addressId, 'shipping');

        static::assertSame(
            ['success' => ['account.addressSaved']],
            $controller->recorder()->flashBag
        );

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testAddressManagerWithBilling(): void
    {
        $addressId = Uuid::randomHex();

        $dataBag = new RequestDataBag([
            'address' => [
                'id' => $addressId,
            ],
        ]);

        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $userAddress = new UserAddressEntity();
        $userAddress->setUniqueIdentifier($addressId);
        $userAddressCollection = new UserAddressCollection([$userAddress]);

        $listAddressRouteResponse = $this->createMock(ListAddressRouteResponse::class);

        $listAddressRoute = $this->createMock(AbstractListAddressRoute::class);
        $listAddressRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($listAddressRouteResponse);

        $listAddressRouteResponse
            ->expects($this->once())
            ->method('getAddressCollection')
            ->willReturn($userAddressCollection);

        $controller = $this->buildController(listAddressRoute: $listAddressRoute);

        $response = $controller->addressManagerUpsert(new Request(), $dataBag, Generator::generateChannelContext(), $user, $addressId, 'billing');

        static::assertSame(
            ['success' => ['account.addressSaved']],
            $controller->recorder()->flashBag
        );

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testAddressManagerHandeltFormViolations(): void
    {
        $addressId = Uuid::randomHex();

        $dataBag = new RequestDataBag([
            'address' => [
                'id' => $addressId,
            ],
        ]);

        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $userAddress = new UserAddressEntity();
        $userAddress->setUniqueIdentifier($addressId);
        $userAddressCollection = new UserAddressCollection([$userAddress]);

        $listAddressRouteResponse = $this->createMock(ListAddressRouteResponse::class);

        $listAddressRoute = $this->createMock(AbstractListAddressRoute::class);
        $listAddressRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($listAddressRouteResponse);

        $listAddressRouteResponse
            ->expects($this->once())
            ->method('getAddressCollection')
            ->willReturn($userAddressCollection);

        $abstractUpsertAddressRoute = $this->createMock(AbstractUpsertAddressRoute::class);
        $abstractUpsertAddressRoute
            ->expects($this->once())
            ->method('upsert')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));

        $controller = $this->buildController(
            listAddressRoute: $listAddressRoute,
            abstractUpsertAddressRoute: $abstractUpsertAddressRoute,
        );

        $response = $controller->addressManagerUpsert(new Request(), $dataBag, Generator::generateChannelContext(), $user, $addressId, 'shipping');

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertArrayHasKey('formViolations', $controller->recorder()->renderFrontendParameters);
    }

    public function testAddressManagerHandeltErrors(): void
    {
        $addressId = Uuid::randomHex();

        $dataBag = new RequestDataBag([
            'address' => [
                'id' => $addressId,
            ],
        ]);

        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $userAddress = new UserAddressEntity();
        $userAddress->setUniqueIdentifier($addressId);
        $userAddressCollection = new UserAddressCollection([$userAddress]);

        $listAddressRouteResponse = $this->createMock(ListAddressRouteResponse::class);

        $listAddressRoute = $this->createMock(AbstractListAddressRoute::class);
        $listAddressRoute
            ->expects($this->once())
            ->method('load')
            ->willReturn($listAddressRouteResponse);

        $listAddressRouteResponse
            ->expects($this->once())
            ->method('getAddressCollection')
            ->willReturn($userAddressCollection);

        $abstractUpsertAddressRoute = $this->createMock(AbstractUpsertAddressRoute::class);
        $abstractUpsertAddressRoute
            ->expects($this->once())
            ->method('upsert')
            ->willThrowException(new \Exception());

        $controller = $this->buildController(
            listAddressRoute: $listAddressRoute,
            abstractUpsertAddressRoute: $abstractUpsertAddressRoute,
        );

        $response = $controller->addressManagerUpsert(new Request(), $dataBag, Generator::generateChannelContext(), $user, $addressId, 'shipping');

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        static::assertArrayHasKey('messages', $controller->recorder()->renderFrontendParameters);

        static::assertSame(
            ['type' => 'danger', 'text' => 'error.message-default'],
            $controller->recorder()->renderFrontendParameters['messages']
        );
    }

}

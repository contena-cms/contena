<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\Member\Channel\AbstractDeleteAddressRoute;
use Contena\Core\System\Member\Channel\AbstractUpsertAddressRoute;
use Contena\Core\System\Member\Exception\AddressNotFoundException;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\Test\Generator;
use Contena\Frontend\Controller\AddressController;
use Contena\Frontend\Page\Address\Detail\AddressDetailPageLoader;
use Contena\Frontend\Page\Address\Listing\AddressListingPageLoader;
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
    private AddressControllerTestClass $controller;

    private AbstractUpsertAddressRoute&Stub $upsertAddressRoute;

    private AbstractDeleteAddressRoute&Stub $deleteAddressRoute;

    protected function setUp(): void
    {
        $this->upsertAddressRoute = static::createStub(AbstractUpsertAddressRoute::class);
        $this->deleteAddressRoute = static::createStub(AbstractDeleteAddressRoute::class);
        $this->controller = $this->buildController();
    }

    public function testAccountAddressOverview(): void
    {
        $member = new MemberEntity();
        $member->setId(Uuid::randomHex());

        $response = $this->controller->accountAddressOverview(new Request(), Generator::generateChannelContext(), $member);

        static::assertSame('@Frontend/frontend/page/account/addressbook/index.html.twig', $this->controller->renderFrontendView);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testAccountCreateAddress(): void
    {
        $member = new MemberEntity();
        $member->setId(Uuid::randomHex());
        $dataBag = new RequestDataBag();
        $dataBag->set('address', new RequestDataBag(['id' => Uuid::randomHex()]));

        $this->controller->accountCreateAddress(new Request(), $dataBag, Generator::generateChannelContext(), $member);

        static::assertArrayHasKey('page', $this->controller->renderFrontendParameters);
        static::assertArrayHasKey('data', $this->controller->renderFrontendParameters);
    }

    public function testAccountEditAddress(): void
    {
        $member = new MemberEntity();
        $member->setId(Uuid::randomHex());
        $request = new Request();
        $request->query->set('redirectTo', 'foo');

        $response = $this->controller->accountEditAddress($request, Generator::generateChannelContext(), $member);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertArrayHasKey('page', $this->controller->renderFrontendParameters);
        static::assertSame('foo', $this->controller->renderFrontendParameters['redirectTo'] ?? null);
    }

    public function testSaveAddress(): void
    {
        $member = new MemberEntity();
        $member->setId(Uuid::randomHex());

        $dataBag = new RequestDataBag();
        $dataBag->set('address', new RequestDataBag(['id' => Uuid::randomHex()]));

        $response = $this->controller->saveAddress($dataBag, Generator::generateChannelContext(), $member, new Request());

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.account.address.page', $response->getTargetUrl());
    }

    public function testSaveAddressWithId(): void
    {
        $member = new MemberEntity();
        $member->setId(Uuid::randomHex());
        $addressId = Uuid::randomHex();

        $dataBag = new RequestDataBag();
        $dataBag->set('address', new RequestDataBag(['id' => $addressId]));

        $upsertAddressRoute = $this->createMock(AbstractUpsertAddressRoute::class);
        $upsertAddressRoute
            ->expects($this->once())
            ->method('upsert')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));

        $controller = $this->buildController(upsertAddressRoute: $upsertAddressRoute);
        $response = $controller->saveAddress($dataBag, Generator::generateChannelContext(), $member, new Request());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('forward to frontend.account.address.edit.page', $response->getContent());
        static::assertSame(['addressId' => $addressId], $controller->forwardToRouteParameters);
    }

    public function testSaveAddressWithoutId(): void
    {
        $member = new MemberEntity();
        $member->setId(Uuid::randomHex());

        $dataBag = new RequestDataBag();
        $dataBag->set('address', new RequestDataBag(['foo' => 'foo']));

        $upsertAddressRoute = $this->createMock(AbstractUpsertAddressRoute::class);
        $upsertAddressRoute
            ->expects($this->once())
            ->method('upsert')
            ->willThrowException(new ConstraintViolationException(new ConstraintViolationList(), []));

        $controller = $this->buildController(upsertAddressRoute: $upsertAddressRoute);
        $response = $controller->saveAddress($dataBag, Generator::generateChannelContext(), $member, new Request());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('forward to frontend.account.address.create.page', $response->getContent());
    }

    public function testDeleteAddressWithNoIdThrowsException(): void
    {
        $this->expectExceptionObject(RoutingException::missingRequestParameter('addressId'));

        $this->controller->deleteAddress('', Generator::generateChannelContext(), new MemberEntity());
    }

    public function testDeleteAddress(): void
    {
        $deleteAddressRoute = $this->createMock(AbstractDeleteAddressRoute::class);
        $deleteAddressRoute->expects($this->once())->method('delete');

        $controller = $this->buildController(deleteAddressRoute: $deleteAddressRoute);
        $response = $controller->deleteAddress(Uuid::randomHex(), Generator::generateChannelContext(), new MemberEntity());

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(['success' => ['account.addressDeleted']], $controller->flashBag);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.account.address.page', $response->getTargetUrl());
    }

    public function testDeleteAddressWithInvalidIdThrowsException(): void
    {
        $addressId = Uuid::randomHex();
        $deleteAddressRoute = $this->createMock(AbstractDeleteAddressRoute::class);
        $deleteAddressRoute
            ->expects($this->once())
            ->method('delete')
            ->willThrowException(new AddressNotFoundException($addressId));

        $controller = $this->buildController(deleteAddressRoute: $deleteAddressRoute);
        $response = $controller->deleteAddress($addressId, Generator::generateChannelContext(), new MemberEntity());

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame(['danger' => ['account.addressNotDeleted']], $controller->flashBag);
        static::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        static::assertSame('frontend.account.address.page', $response->getTargetUrl());
    }

    private function buildController(
        ?AbstractUpsertAddressRoute $upsertAddressRoute = null,
        ?AbstractDeleteAddressRoute $deleteAddressRoute = null,
    ): AddressControllerTestClass {
        $controller = new AddressControllerTestClass(
            static::createStub(AddressListingPageLoader::class),
            static::createStub(AddressDetailPageLoader::class),
            $upsertAddressRoute ?? $this->upsertAddressRoute,
            $deleteAddressRoute ?? $this->deleteAddressRoute,
        );

        $translator = static::createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => $key);

        $containerBuilder = new ContainerBuilder();
        $containerBuilder->set('request_stack', new RequestStack());
        $containerBuilder->set('translator', $translator);
        $controller->setContainer($containerBuilder);

        return $controller;
    }
}

/**
 * @internal
 */
class AddressControllerTestClass extends AddressController
{
    use FrontendControllerMockTrait;
}

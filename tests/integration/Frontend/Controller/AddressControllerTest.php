<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Script\Debugging\ScriptTraces;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Exception\InvalidUuidException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressCollection;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Controller\AddressController;
use Contena\Frontend\Event\FrontendRenderEvent;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Contena\Core\Framework\Test\Seo\FrontendChannelTestHelper;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;

/**
 * @internal
 */
class AddressControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendControllerTestBehaviour;
    use FrontendChannelTestHelper;

    private const ADDRESS_TYPE_BILLING = 'billing';
    private const ADDRESS_TYPE_SHIPPING = 'shipping';

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    private string $addressId;

    private UserEntity $loginUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userRepository = static::getContainer()->get('user.repository');

        $this->addressId = Uuid::randomHex();
    }

    public function testDeleteAddressOfOtherUser(): void
    {
        [$id1, $id2] = $this->createUsers();

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL, [ChannelContextService::CUSTOMER_ID => $id1]);

        $user = $context->getUser();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame($id1, $user->getId());

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::FRONTEND_URL, 'contena.test');
        $request->setSession($this->getSession());
        static::getContainer()->get('request_stack')->push($request);

        $controller->deleteAddress($id2, $request, $context, $user);

        $criteria = new Criteria([$id2]);

        /** @var EntityRepository<UserAddressCollection> $repository */
        $repository = static::getContainer()->get('user_address.repository');
        $address = $repository->search($criteria, $context->getContext())->getEntities()
            ->get($id2);

        static::assertInstanceOf(UserAddressEntity::class, $address);

        $controller->deleteAddress($id1, $request, $context, $user);

        $criteria = new Criteria([$id1]);

        /** @var EntityRepository<UserAddressCollection> $repository */
        $repository = static::getContainer()->get('user_address.repository');
        $exists = $repository
            ->search($criteria, $context->getContext())->getEntities()
            ->has($id2);

        static::assertFalse($exists);
    }

    public function testAddressListingPageLoadedScriptsAreExecuted(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/account/address');
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = $browser->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey('address-listing-page-loaded', $traces);
    }

    public function testAddressDetailPageLoadedScriptsAreExecutedOnAddressCreate(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/account/address/create');
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = $browser->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey('address-detail-page-loaded', $traces);
    }

    public function testAddressDetailPageLoadedScriptsAreExecutedOnAddressEdit(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/account/address/' . $this->addressId);
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = $browser->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey('address-detail-page-loaded', $traces);
    }

    public function testCheckoutSwitchDefaultShippingAddress(): void
    {
        [$id1] = $this->createUsers();

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL, [ChannelContextService::CUSTOMER_ID => $id1]);

        $user = $context->getUser();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame($id1, $user->getId());

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::FRONTEND_URL, 'contena.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        $newDefaultShippingAddress = $this->createUserAddress($id1);

        $dataBag = new RequestDataBag();
        $dataBag->set('type', 'shipping');
        $dataBag->set('id', $newDefaultShippingAddress);

        $controller->checkoutSwitchDefaultAddress($request, $dataBag, $context, $user);

        /** @var EntityRepository<UserCollection> $repo */
        $repo = static::getContainer()->get('user.repository');

        $user = $repo->search(new Criteria([$id1]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame($user->getDefaultShippingAddressId(), $newDefaultShippingAddress);
    }

    public function testSaveAddress(): void
    {
        [$id1] = $this->createUsers();

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL, [ChannelContextService::CUSTOMER_ID => $id1]);

        $user = $context->getUser();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame($id1, $user->getId());

        $controller = static::getContainer()->get(AddressController::class);

        $dataBag = new RequestDataBag([
            'address' => [
                'userId' => $user->getId(),
                'name' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
        ]);

        /** @var EntityRepository<UserCollection> $repo */
        $repo = static::getContainer()->get('user.repository');

        $criteria = new Criteria([$id1]);
        $criteria->addAssociation('addresses');

        $userWithOldAddress = $repo->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(UserEntity::class, $userWithOldAddress);
        static::assertNotNull($userWithOldAddress->getAddresses());
        static::assertCount(1, $userWithOldAddress->getAddresses());

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::FRONTEND_URL, 'contena.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        /** @var RedirectResponse $response */
        $response = $controller->saveAddress($dataBag, $context, $user, new Request());

        $criteria = new Criteria([$id1]);
        $criteria->addAssociation('addresses');

        $userNewAddress = $repo->search($criteria, Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(FlashBagAwareSessionInterface::class, $this->getSession());
        static::assertSame(
            ['success' => [static::getContainer()->get('translator')->trans('account.addressSaved')]],
            $this->getSession()->getFlashBag()->all()
        );
        static::assertTrue($response->isRedirect(), (string) $response->getContent());
        static::assertInstanceOf(UserEntity::class, $userNewAddress);
        static::assertNotNull($userNewAddress->getAddresses());
        static::assertCount(2, $userNewAddress->getAddresses());
    }

    public function testCheckoutSwitchDefaultBillingAddress(): void
    {
        [$id1] = $this->createUsers();

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL, [ChannelContextService::CUSTOMER_ID => $id1]);

        $user = $context->getUser();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame($id1, $user->getId());

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::FRONTEND_URL, 'contena.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        $newDefaultBillingAddress = $this->createUserAddress($id1);

        $dataBag = new RequestDataBag();
        $dataBag->set('type', 'billing');
        $dataBag->set('id', $newDefaultBillingAddress);

        $controller->checkoutSwitchDefaultAddress($request, $dataBag, $context, $user);

        /** @var EntityRepository<UserCollection> $repo */
        $repo = static::getContainer()->get('user.repository');

        $user = $repo->search(new Criteria([$id1]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame($user->getDefaultBillingAddressId(), $newDefaultBillingAddress);
    }

    public function testCheckoutSwitchDefaultAddressWithInvalidType(): void
    {
        [$id1] = $this->createUsers();

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL, [ChannelContextService::CUSTOMER_ID => $id1]);

        $user = $context->getUser();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame($id1, $user->getId());

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::FRONTEND_URL, 'contena.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        $dataBag = new RequestDataBag();
        $dataBag->set('type', 'foo');

        static::expectException(RoutingException::class);

        $controller->checkoutSwitchDefaultAddress($request, $dataBag, $context, $user);
    }

    public function testSwitchDefaultAddressWithInvalidUuid(): void
    {
        [$id1] = $this->createUsers();

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL, [ChannelContextService::CUSTOMER_ID => $id1]);

        $user = $context->getUser();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame($id1, $user->getId());

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::FRONTEND_URL, 'contena.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        static::expectException(InvalidUuidException::class);

        $controller->switchDefaultAddress(self::ADDRESS_TYPE_SHIPPING, 'foo', $context, $user);
    }

    public function testSwitchDefaultAddressWithInvalidType(): void
    {
        [$id1] = $this->createUsers();

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL, [ChannelContextService::CUSTOMER_ID => $id1]);

        $user = $context->getUser();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame($id1, $user->getId());

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::FRONTEND_URL, 'contena.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        /** @var RedirectResponse $response */
        $response = $controller->switchDefaultAddress('foo', $user->getDefaultBillingAddressId(), $context, $user);

        static::assertInstanceOf(FlashBagAwareSessionInterface::class, $this->getSession());
        static::assertSame(
            ['danger' => [static::getContainer()->get('translator')->trans('account.addressDefaultNotChanged')]],
            $this->getSession()->getFlashBag()->all()
        );
        static::assertTrue($response->isRedirect(), (string) $response->getContent());
    }

    public function testSwitchDefaultShippingAddress(): void
    {
        [$id1] = $this->createUsers();

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL, [ChannelContextService::CUSTOMER_ID => $id1]);

        $user = $context->getUser();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame($id1, $user->getId());

        $controller = static::getContainer()->get(AddressController::class);

        $newDefaultShippingAddress = $this->createUserAddress($id1);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::FRONTEND_URL, 'contena.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        /** @var RedirectResponse $response */
        $response = $controller->switchDefaultAddress(self::ADDRESS_TYPE_SHIPPING, $newDefaultShippingAddress, $context, $user);

        /** @var EntityRepository<UserCollection> $repo */
        $repo = static::getContainer()->get('user.repository');

        $user = $repo->search(new Criteria([$id1]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($user);
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertTrue($response->isRedirect(), (string) $response->getContent());
        static::assertInstanceOf(FlashBagAwareSessionInterface::class, $this->getSession());
        static::assertSame(
            ['success' => [static::getContainer()->get('translator')->trans('account.addressDefaultChanged')]],
            $this->getSession()->getFlashBag()->all()
        );
        static::assertSame($newDefaultShippingAddress, $user->getDefaultShippingAddressId());
    }

    public function testSwitchDefaultBillingAddress(): void
    {
        [$id1] = $this->createUsers();

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL, [ChannelContextService::CUSTOMER_ID => $id1]);

        $user = $context->getUser();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame($id1, $user->getId());

        $controller = static::getContainer()->get(AddressController::class);

        $newDefaultBillingAddress = $this->createUserAddress($id1);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::FRONTEND_URL, 'contena.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        /** @var RedirectResponse $response */
        $response = $controller->switchDefaultAddress(self::ADDRESS_TYPE_BILLING, $newDefaultBillingAddress, $context, $user);

        /** @var EntityRepository<UserCollection> $repo */
        $repo = static::getContainer()->get('user.repository');

        $user = $repo->search(new Criteria([$id1]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertNotNull($user);
        static::assertInstanceOf(UserEntity::class, $user);

        static::assertInstanceOf(FlashBagAwareSessionInterface::class, $this->getSession());
        static::assertSame(
            ['success' => [static::getContainer()->get('translator')->trans('account.addressDefaultChanged')]],
            $this->getSession()->getFlashBag()->all()
        );
        static::assertTrue($response->isRedirect(), (string) $response->getContent());
        static::assertSame($newDefaultBillingAddress, $user->getDefaultBillingAddressId());
    }

    public function testAddressManagerSwitchActiveShippingAddress(): void
    {
        [$id1] = $this->createUsers();

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL, [ChannelContextService::CUSTOMER_ID => $id1]);

        $user = $context->getUser();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame($id1, $user->getId());

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::FRONTEND_URL, 'contena.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        $newActiveAddress = $this->createUserAddress($id1);
        $request->request->set(ChannelContextService::SHIPPING_ADDRESS_ID, $newActiveAddress);

        $controller->addressManagerSwitch($request, $context);

        $newContext = static::getContainer()->get(ChannelContextPersister::class)->load($context->getToken(), TestDefaults::CHANNEL);

        static::assertIsArray($newContext);
        static::assertArrayHasKey(ChannelContextService::SHIPPING_ADDRESS_ID, $newContext);
        static::assertSame($newActiveAddress, $newContext[ChannelContextService::SHIPPING_ADDRESS_ID]);
    }

    public function testAddressManagerSwitchActiveBillingAddress(): void
    {
        [$id1] = $this->createUsers();

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL, [ChannelContextService::CUSTOMER_ID => $id1]);

        $user = $context->getUser();
        static::assertInstanceOf(UserEntity::class, $user);
        static::assertSame($id1, $user->getId());

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::FRONTEND_URL, 'contena.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        $newActiveAddress = $this->createUserAddress($id1);
        $request->request->set(ChannelContextService::BILLING_ADDRESS_ID, $newActiveAddress);

        $controller->addressManagerSwitch($request, $context);

        $newContext = static::getContainer()->get(ChannelContextPersister::class)->load($context->getToken(), TestDefaults::CHANNEL);

        static::assertIsArray($newContext);
        static::assertArrayHasKey(ChannelContextService::BILLING_ADDRESS_ID, $newContext);
        static::assertSame($newActiveAddress, $newContext[ChannelContextService::BILLING_ADDRESS_ID]);
    }

    public function testAddressManagerExceptionWhenCreating(): void
    {
        [$userId] = $this->createUsers();

        $context = static::getContainer()
            ->get(ChannelContextFactory::class)
            ->create(
                Uuid::randomHex(),
                TestDefaults::CHANNEL,
                [
                    ChannelContextService::CUSTOMER_ID => $userId,
                ]
            );

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::FRONTEND_URL, 'contena.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        $user = $context->getUser();
        static::assertNotNull($user);

        $dataBag = new RequestDataBag([
            'address' => [
                'name' => 'not',
                'company' => 'not',
                'department' => 'not',
                'street' => 'not',
            ],
        ]);

        $renderEvent = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            FrontendRenderEvent::class,
            static function (FrontendRenderEvent $event) use (&$renderEvent): void {
                $renderEvent = $event;
            },
            true
        );

        $controller->addressManagerUpsert($request, $dataBag, $context, $user, null, self::ADDRESS_TYPE_SHIPPING);

        static::assertInstanceOf(FrontendRenderEvent::class, $renderEvent);
        $data = $renderEvent->getParameters();
        static::assertArrayHasKey('formViolations', $data);
        static::assertArrayHasKey('postedAddress', $data);
    }

    public function testAccountAddressOverview(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/account/address');

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testAccountCreateAddress(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/account/address/create');

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testAccountEditAddressWithWrongId(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/account/address/edit/1');

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testAccountEditAddress(): void
    {
        $browser = $this->login();

        $browser->request('GET', \sprintf(
            '/account/address/%s',
            $this->loginUser->getDefaultBillingAddressId()
        ));

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testAddressManagerGet(): void
    {
        $browser = $this->login();

        $browser->request('GET', '/widgets/account/address-manager');

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testAddressManagerCreateFormUsesTextInputType(): void
    {
        [$userId] = $this->createUsers();

        $context = static::getContainer()
            ->get(ChannelContextFactory::class)
            ->create(
                Uuid::randomHex(),
                TestDefaults::CHANNEL,
                [
                    ChannelContextService::CUSTOMER_ID => $userId,
                ]
            );

        $controller = static::getContainer()->get(AddressController::class);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $context);
        $request->attributes->set(RequestTransformer::FRONTEND_URL, 'contena.test');
        $request->setSession($this->getSession());

        static::getContainer()->get('request_stack')->push($request);

        $user = $context->getUser();
        static::assertNotNull($user);

        foreach ([self::ADDRESS_TYPE_SHIPPING, self::ADDRESS_TYPE_BILLING] as $addressType) {
            $response = $controller->addressManagerUpsert($request, new RequestDataBag(), $context, $user, null, $addressType);

            static::assertSame(Response::HTTP_OK, $response->getStatusCode(), 'Failed for address type: ' . $addressType);

            $content = (string) $response->getContent();

            static::assertMatchesRegularExpression(
                '/<input(?=[^>]*name="address\[firstName\]")(?=[^>]*type="text")[^>]*>/',
                $content,
                'First name input must use type="text" for address type: ' . $addressType
            );
            static::assertMatchesRegularExpression(
                '/<input(?=[^>]*name="address\[street\]")(?=[^>]*type="text")[^>]*>/',
                $content,
                'Street input must use type="text" for address type: ' . $addressType
            );
            static::assertDoesNotMatchRegularExpression(
                '/<input(?=[^>]*class="[^"]*form-control[^"]*")(?=[^>]*type="' . $addressType . '")[^>]*>/',
                $content,
                'Form control inputs must not use invalid type="' . $addressType . '"'
            );
        }
    }

    private function login(): KernelBrowser
    {
        $this->loginUser = $this->createUser();

        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $this->loginUser->getEmail(),
                'password' => 'test12345',
            ])
        );
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return $browser;
    }

    private function createUser(): UserEntity
    {
        $userId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => [
                'id' => $this->addressId,
                'name' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $this->addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => 'test@example.com',
            'password' => 'test12345',
            'name' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => '12345',
        ];

        $context = Context::createDefaultContext();

        /** @var EntityRepository<UserCollection> $repo */
        $repo = static::getContainer()->get('user.repository');

        $repo->create([$user], $context);

        $user = $repo->search(new Criteria([$userId]), $context)
            ->getEntities()
            ->first();

        static::assertNotNull($user);

        return $user;
    }

    /**
     * @return array<int, string>
     */
    private function createUsers(): array
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $salutationId = $this->getValidSalutationId();

        $users = [
            [
                'id' => $id1,
                'channelId' => TestDefaults::CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $id1,
                    'name' => 'not',
                    'lastName' => 'not',
                    'city' => 'not',
                    'street' => 'not',
                    'zipcode' => 'not',
                    'salutationId' => $salutationId,
                    'country' => ['name' => 'not'],
                ],
                'defaultBillingAddressId' => $id1,
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::randomHex() . '@example.com',
                'password' => 'not12345',
                'lastName' => 'not',
                'name' => 'First name',
                'salutationId' => $salutationId,
                'userNumber' => 'not',
            ],
            [
                'id' => $id2,
                'channelId' => TestDefaults::CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $id2,
                    'name' => 'not',
                    'lastName' => 'not',
                    'city' => 'not',
                    'street' => 'not',
                    'zipcode' => 'not',
                    'salutationId' => $salutationId,
                    'country' => ['name' => 'not'],
                ],
                'defaultBillingAddressId' => $id2,
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::randomHex() . '@example.com',
                'password' => 'not12345',
                'lastName' => 'not',
                'name' => 'First name',
                'salutationId' => $salutationId,
                'userNumber' => 'not',
            ],
        ];

        $this->userRepository->create($users, Context::createDefaultContext());

        return [$id1, $id2];
    }

    private function createUserAddress(string $userId): string
    {
        $newBillingAddressId = Uuid::randomHex();
        $repository = static::getContainer()->get('user_address.repository');
        $repository->create([
            [
                'id' => $newBillingAddressId,
                'userId' => $userId,
                'name' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
        ], Context::createDefaultContext());

        return $newBillingAddressId;
    }

    private function getValidCountryId(?string $channelId = TestDefaults::CHANNEL): string
    {
        /** @var EntityRepository<CountryCollection> $repository */
        $repository = static::getContainer()->get('country.repository');

        $criteria = (new Criteria())->setLimit(1)
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('shippingAvailable', true));

        if ($channelId !== null) {
            $criteria->addFilter(new EqualsFilter('channels.id', $channelId));
        }

        return (string) $repository->searchIds($criteria, Context::createDefaultContext())->firstId();
    }
}

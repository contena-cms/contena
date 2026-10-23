<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Contena\Core\Checkout\Order\OrderStates;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Debugging\ScriptTraces;
use Contena\Core\Framework\Test\TestCaseBase\CountryAddToChannelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Integration\Traits\OrderFixture;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Event\RouteRequest\OrderRouteRequestEvent;
use Contena\Frontend\Event\FrontendRenderEvent;
use Contena\Frontend\Page\Account\Order\AccountEditOrderPageLoadedHook;
use Contena\Frontend\Page\Account\Order\AccountOrderDetailPageLoadedHook;
use Contena\Frontend\Page\Account\Order\AccountOrderPageLoadedHook;
use Contena\Frontend\Test\Controller\FrontendControllerTestBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class AccountOrderControllerTest extends TestCase
{
    use CountryAddToChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use OrderFixture;
    use FrontendControllerTestBehaviour;

    /**
     * @var EntityRepository<ChannelCollection>
     */
    private EntityRepository $channelRepository;

    protected function setUp(): void
    {
        $this->addCountriesToChannel();
        $this->channelRepository = static::getContainer()->get('channel.repository');
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    public function testAjaxOrderDetail(): void
    {
        // the route throws under the flag (triggerDeprecationOrThrow), leaving the render-event assertions unreached
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $context = Context::createDefaultContext();
        $user = $this->createUser($context);
        $browser = $this->login($user->getEmail());

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $context);
        $orderData[0]['orderUser']['user'] = ['id' => $user->getId()];

        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_FRONTEND))
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('domains.url', $_SERVER['APP_URL']));

        $channel = $this->channelRepository->search($criteria, $context)->getEntities()->first();

        if ($channel !== null) {
            $orderData[0]['channelId'] = $channel->getId();
        }

        $blogId = $this->createBlog($context);
        $orderData[0]['lineItems'][0]['identifier'] = $blogId;
        $orderData[0]['lineItems'][0]['blogId'] = $blogId;

        $orderRepo = static::getContainer()->get('order.repository');
        $orderRepo->create($orderData, $context);

        $renderEvent = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            FrontendRenderEvent::class,
            static function (FrontendRenderEvent $event) use (&$renderEvent): void {
                $renderEvent = $event;
            },
            true
        );

        $browser->request('GET', $_SERVER['APP_URL'] . '/widgets/account/order/detail/' . $orderId);

        static::assertInstanceOf(FrontendRenderEvent::class, $renderEvent);
        $orderLineItemCollection = $renderEvent->getParameters()['orderDetails'];
        static::assertInstanceOf(OrderLineItemCollection::class, $orderLineItemCollection);
        foreach ($orderLineItemCollection as $orderLineItemEntity) {
            static::assertNull($orderLineItemEntity->getBlog());
        }

        EventHookDispatcher::fromContainer(static::getContainer())->on(OrderRouteRequestEvent::class, static function (OrderRouteRequestEvent $event): void {
            $event->getCriteria()->addAssociation('lineItems.blog');
        });

        $renderEvent = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            FrontendRenderEvent::class,
            static function (FrontendRenderEvent $event) use (&$renderEvent): void {
                $renderEvent = $event;
            },
            true
        );

        $browser->request('GET', $_SERVER['APP_URL'] . '/widgets/account/order/detail/' . $orderId);

        static::assertInstanceOf(FrontendRenderEvent::class, $renderEvent);
        $orderLineItemCollection = $renderEvent->getParameters()['orderDetails'];
        static::assertInstanceOf(OrderLineItemCollection::class, $orderLineItemCollection);
        foreach ($orderLineItemCollection as $orderLineItemEntity) {
            static::assertNotNull($orderLineItemEntity->getBlog());
        }
    }

    public function testGuestUserGetsRedirectedToAuth(): void
    {
        $context = Context::createDefaultContext();
        $user = $this->createUser($context, true);
        $browser = $this->login($user->getEmail());

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $context);
        $orderData[0]['orderUser']['user']['id'] = $user->getId();
        $orderData[0]['orderNumber'] = 'order-number';

        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_FRONTEND))
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('domains.url', $_SERVER['APP_URL']));

        $channel = $this->channelRepository->search($criteria, $context)->getEntities()->first();
        if ($channel !== null) {
            $orderData[0]['channelId'] = $channel->getId();
        }

        $blogId = $this->createBlog($context);
        $orderData[0]['lineItems'][0]['identifier'] = $blogId;
        $orderData[0]['lineItems'][0]['blogId'] = $blogId;

        $orderRepo = static::getContainer()->get('order.repository');
        $orderRepo->create($orderData, $context);

        $browser->followRedirects();

        $renderEvent = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            FrontendRenderEvent::class,
            static function (FrontendRenderEvent $event) use (&$renderEvent): void {
                $renderEvent = $event;
            },
            true
        );

        $browser->request('GET', $_SERVER['APP_URL'] . '/account/order/' . $orderData[0]['deepLinkCode']);

        static::assertInstanceOf(FrontendRenderEvent::class, $renderEvent);
        $data = $renderEvent->getParameters();
        static::assertSame('frontend.account.order.single.page', $data['redirectTo']);
        static::assertSame('BwvdEInxOHBbwfRw6oHF1Q_orfYeo9RY', $data['redirectParameters']['deepLinkCode']);

        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/order/' . $orderData[0]['deepLinkCode'],
            $this->tokenize('frontend.account.login', [
                'email' => $user->getEmail(),
                'zipcode' => $orderData[0]['orderUser']['user']['addresses'][0]['zipcode'],
            ])
        );

        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testEditOrderWithDifferentChannelContextShippingMethodRestoresOrderShippingMethod(): void
    {
        $context = Context::createDefaultContext();
        $user = $this->createUser($context);

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $context);
        $orderData[0]['orderUser']['user']['id'] = $user->getId();
        $orderData[0]['orderUser']['user']['guest'] = false;
        $orderData[0]['orderNumber'] = 'order-number';
        $orderData[0]['primaryOrderDeliveryId'] = $orderData[0]['deliveries'][0]['id'];

        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_FRONTEND))
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('domains.url', $_SERVER['APP_URL']));

        $channel = $this->channelRepository->search($criteria, $context)->getEntities()->first();
        static::assertNotNull($channel);

        $orderData[0]['channelId'] = $channel->getId();

        $blogId = $this->createBlog($context);
        $orderData[0]['lineItems'][0]['identifier'] = $blogId;
        $orderData[0]['lineItems'][0]['blogId'] = $blogId;

        $orderRepo = static::getContainer()->get('order.repository');
        $orderRepo->create($orderData, $context);

        // Change default Channel ShippingMethod to another than the ordered one
        $orderShippingMethodId = $orderData[0]['deliveries'][0]['shippingMethodId'];
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(
            new NotFilter(NotFilter::CONNECTION_AND, [
                new EqualsFilter('id', $orderShippingMethodId),
            ]),
            new EqualsFilter('active', true)
        );
        $differentShippingMethodId = static::getContainer()->get('shipping_method.repository')->searchIds($criteria, $context)->firstId();
        static::assertNotNull($differentShippingMethodId);
        static::assertNotSame($orderShippingMethodId, $differentShippingMethodId);
        $this->channelRepository->update([
            [
                'id' => $channel->getId(),
                'shippingMethodId' => $differentShippingMethodId,
                'shippingMethods' => [
                    [
                        'id' => $differentShippingMethodId,
                    ],
                    [
                        'id' => $orderShippingMethodId,
                    ],
                ],
            ],
        ], $context);

        $browser = $this->login($user->getEmail());
        $browser->followRedirects();

        $renderEvent = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            FrontendRenderEvent::class,
            static function (FrontendRenderEvent $event) use (&$renderEvent): void {
                $renderEvent = $event;
            },
            true
        );

        // Load home page to verify the saleschannel got a different shipping method from the ordered one
        $browser->request(
            'GET',
            $_SERVER['APP_URL'] . '/'
        );

        static::assertInstanceOf(FrontendRenderEvent::class, $renderEvent);
        static::assertSame($differentShippingMethodId, $renderEvent->getChannelContext()->getShippingMethod()->getId());

        $renderEvent = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            FrontendRenderEvent::class,
            static function (FrontendRenderEvent $event) use (&$renderEvent): void {
                $renderEvent = $event;
            },
            true
        );

        // Test that the order edit page switches the ChannelContext Shipping method to the order one
        $browser->request(
            'GET',
            $_SERVER['APP_URL'] . '/account/order/edit/' . $orderData[0]['id']
        );

        static::assertInstanceOf(FrontendRenderEvent::class, $renderEvent);
        static::assertSame($orderShippingMethodId, $renderEvent->getChannelContext()->getShippingMethod()->getId());
    }

    public function testAccountOrderPageLoadedScriptsAreExecuted(): void
    {
        $context = Context::createDefaultContext();
        $user = $this->createUser($context);
        $browser = $this->login($user->getEmail());

        $browser->request(
            'GET',
            '/account/order'
        );
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = $browser->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(AccountOrderPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testAccountOrderPageLoadedScriptsAreExecutedForDeeplinkedPage(): void
    {
        $context = Context::createDefaultContext();
        $user = $this->createUser($context);

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $context);
        $orderData[0]['orderUser']['user']['id'] = $user->getId();
        $orderData[0]['orderUser']['user']['guest'] = false;

        $orderRepo = static::getContainer()->get('order.repository');
        $orderRepo->create($orderData, $context);

        $browser = $this->login($user->getEmail());

        $browser->request(
            'GET',
            '/account/order/' . $orderData[0]['deepLinkCode']
        );
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = $browser->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(AccountOrderPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testOrderOverviewShowsCancelActionOnlyForOpenOrders(): void
    {
        $context = Context::createDefaultContext();
        $user = $this->createUser($context);

        $openOrderId = Uuid::randomHex();
        $openOrderData = $this->getOrderData($openOrderId, $context);
        $openOrderData[0]['orderUser']['user']['id'] = $user->getId();
        $openOrderData[0]['orderUser']['user']['guest'] = false;
        $openOrderData[0]['channelId'] = $this->getFrontendChannelId($context);
        $openOrderData[0]['deepLinkCode'] = Uuid::randomHex();

        $completedOrderId = Uuid::randomHex();
        $completedOrderData = $this->getOrderData($completedOrderId, $context);
        $completedOrderData[0]['orderUser']['user']['id'] = $user->getId();
        $completedOrderData[0]['orderUser']['user']['guest'] = false;
        $completedOrderData[0]['channelId'] = $this->getFrontendChannelId($context);
        $completedOrderData[0]['deepLinkCode'] = Uuid::randomHex();
        $completedOrderData[0]['stateId'] = $this->getStateMachineState(OrderStates::STATE_MACHINE, OrderStates::STATE_COMPLETED);

        static::getContainer()->get('order.repository')->create([$openOrderData[0]], $context);
        static::getContainer()->get('order.repository')->create([$completedOrderData[0]], $context);

        static::getContainer()->get(SystemConfigService::class)->set('core.cart.enableOrderRefunds', true);

        $browser = $this->login($user->getEmail());
        $browser->request('GET', '/account/order');

        $response = $browser->getResponse();
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);
        static::assertStringContainsString('cancelOrderModal-' . $openOrderId, $content);
        static::assertStringNotContainsString('cancelOrderModal-' . $completedOrderId, $content);
    }

    public function testEditOrderPageShowsCancelActionOnlyForOpenOrders(): void
    {
        $context = Context::createDefaultContext();
        $user = $this->createUser($context);

        $openOrderId = Uuid::randomHex();
        $openOrderData = $this->getOrderData($openOrderId, $context);
        $openOrderData[0]['orderUser']['user']['id'] = $user->getId();
        $openOrderData[0]['orderUser']['user']['guest'] = false;
        $openOrderData[0]['channelId'] = $this->getFrontendChannelId($context);
        $openOrderData[0]['deepLinkCode'] = Uuid::randomHex();

        $completedOrderId = Uuid::randomHex();
        $completedOrderData = $this->getOrderData($completedOrderId, $context);
        $completedOrderData[0]['orderUser']['user']['id'] = $user->getId();
        $completedOrderData[0]['orderUser']['user']['guest'] = false;
        $completedOrderData[0]['channelId'] = $this->getFrontendChannelId($context);
        $completedOrderData[0]['deepLinkCode'] = Uuid::randomHex();
        $completedOrderData[0]['stateId'] = $this->getStateMachineState(OrderStates::STATE_MACHINE, OrderStates::STATE_COMPLETED);

        static::getContainer()->get('order.repository')->create([$openOrderData[0]], $context);
        static::getContainer()->get('order.repository')->create([$completedOrderData[0]], $context);

        static::getContainer()->get(SystemConfigService::class)->set('core.cart.enableOrderRefunds', true);

        $browser = $this->login($user->getEmail());

        $browser->request('GET', '/account/order/edit/' . $openOrderId);
        $openContent = (string) $browser->getResponse()->getContent();

        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), $openContent);
        static::assertStringContainsString('edit-order-cancel-order-modal-toggle-btn', $openContent);

        $browser->request('GET', '/account/order/edit/' . $completedOrderId);
        $completedContent = (string) $browser->getResponse()->getContent();

        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), $completedContent);
        static::assertStringNotContainsString('edit-order-cancel-order-modal-toggle-btn', $completedContent);
    }

    public function testEditOrderPagePreselectsThePaymentMethodOfTheOrder(): void
    {
        $context = Context::createDefaultContext();
        $user = $this->createUser($context);
        $channelId = $this->getFrontendChannelId($context);

        $paymentMethodId = $this->createAfterOrderPaymentMethod($context, $channelId, 'Payment method of the order');
        $orderId = $this->createOrderWithTransaction($context, $user, $channelId, $paymentMethodId);

        // the context of the logged in user uses the default payment method of the sales channel
        $browser = $this->login($user->getEmail());
        $crawler = $browser->request('GET', '/account/order/edit/' . $orderId);

        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        static::assertSame('checked', $crawler->filter('#paymentMethod' . $paymentMethodId)->attr('checked'));
        static::assertSame($paymentMethodId, $crawler->filter('#confirmOrderForm input[name="paymentMethodId"]')->attr('value'));
    }

    public function testEditOrderPageShowsThePaymentMethodTheUserSelected(): void
    {
        $context = Context::createDefaultContext();
        $user = $this->createUser($context);
        $channelId = $this->getFrontendChannelId($context);

        $paymentMethodId = $this->createAfterOrderPaymentMethod($context, $channelId, 'Payment method of the order');
        $selectedPaymentMethodId = $this->createAfterOrderPaymentMethod($context, $channelId, 'Payment method of the user');
        $orderId = $this->createOrderWithTransaction($context, $user, $channelId, $paymentMethodId);

        $browser = $this->login($user->getEmail());
        $browser->followRedirects();

        $crawler = $browser->request(
            'POST',
            '/account/order/payment/' . $orderId,
            $this->tokenize('frontend.account.edit-order.change-payment-method', ['paymentMethodId' => $selectedPaymentMethodId])
        );

        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        static::assertSame('checked', $crawler->filter('#paymentMethod' . $selectedPaymentMethodId)->attr('checked'));
        static::assertSame($selectedPaymentMethodId, $crawler->filter('#confirmOrderForm input[name="paymentMethodId"]')->attr('value'));
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    public function testAccountOrderDetailPageLoadedScriptsAreExecuted(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $context = Context::createDefaultContext();
        $user = $this->createUser($context);

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $context);
        $orderData[0]['orderUser']['user']['id'] = $user->getId();
        $orderData[0]['orderUser']['user']['guest'] = false;

        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_FRONTEND))
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('domains.url', $_SERVER['APP_URL']));

        $channel = $this->channelRepository->search($criteria, $context)->getEntities()->first();
        static::assertNotNull($channel);
        $orderData[0]['channelId'] = $channel->getId();

        $orderRepo = static::getContainer()->get('order.repository');
        $orderRepo->create($orderData, $context);
        $browser = $this->login($user->getEmail());
        $browser->request(
            'GET',
            '/widgets/account/order/detail/' . $orderData[0]['id']
        );
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $traces = $browser->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(AccountOrderDetailPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testAccountOrderEditPageLoadedScriptsAreExecuted(): void
    {
        $context = Context::createDefaultContext();
        $user = $this->createUser($context);

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $context);
        $orderData[0]['orderUser']['user']['id'] = $user->getId();
        $orderData[0]['orderUser']['user']['guest'] = false;

        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_FRONTEND))
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('domains.url', $_SERVER['APP_URL']));

        $channel = $this->channelRepository->search($criteria, $context)->getEntities()->first();
        static::assertNotNull($channel);
        $orderData[0]['channelId'] = $channel->getId();

        $orderRepo = static::getContainer()->get('order.repository');
        $orderRepo->create($orderData, $context);

        $browser = $this->login($user->getEmail());
        $url = '/account/order/edit/' . $orderData[0]['id'];

        $browser->request(
            'GET',
            $url
        );
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $url . $response->getContent());

        $traces = $browser->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(AccountEditOrderPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testOrderPageRendersWithMalformedTrackingUrl(): void
    {
        $context = Context::createDefaultContext();
        $user = $this->createUser($context);

        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $context);
        $orderData[0]['orderUser']['user']['id'] = $user->getId();
        $orderData[0]['orderUser']['user']['guest'] = false;
        $orderData[0]['channelId'] = $this->getFrontendChannelId($context);

        static::getContainer()->get('order.repository')->create([$orderData[0]], $context);

        // Malformed tracking URL: a stray "%" that is not a valid sprintf specifier
        static::getContainer()->get('shipping_method.repository')->update([
            [
                'id' => $orderData[0]['deliveries'][0]['shippingMethodId'],
                'trackingUrl' => 'https://tracking.com/test?t=%',
            ],
        ], $context);

        $browser = $this->login($user->getEmail());
        $browser->request('GET', '/account/order');

        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testOrderOverviewHidesLinksForUnavailableBlogs(): void
    {
        $context = Context::createDefaultContext();
        $user = $this->createUser($context);
        $channelId = $this->getFrontendChannelId($context);

        static::getContainer()->get(SystemConfigService::class)->set('core.cart.wishlistEnabled', true);

        $availableBlogId = $this->createBlog($context, 'Available blog', $channelId);
        $deactivatedBlogId = $this->createBlog($context, 'Deactivated blog', $channelId);
        $deletedBlogId = $this->createBlog($context, 'Deleted blog', $channelId);

        $this->createOrderWithBlogs($context, $user, $channelId, [
            $availableBlogId => 'Available blog',
            $deactivatedBlogId => 'Deactivated blog',
            $deletedBlogId => 'Deleted blog',
        ]);

        static::getContainer()->get('blog.repository')->update([
            ['id' => $deactivatedBlogId, 'active' => false],
        ], $context);
        static::getContainer()->get('blog.repository')->delete([
            ['id' => $deletedBlogId],
        ], $context);

        $browser = $this->login($user->getEmail());
        $browser->request('GET', '/account/order');

        $response = $browser->getResponse();
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);

        // only an available blog gets a link, and only a link carries `title`
        static::assertStringContainsString('title="Available blog"', $content);
        static::assertStringNotContainsString('title="Deactivated blog"', $content);
        static::assertStringNotContainsString('title="Deleted blog"', $content);

        static::assertStringContainsString('blog-wishlist-' . $availableBlogId, $content);
        static::assertStringNotContainsString('blog-wishlist-' . $deactivatedBlogId, $content);
        static::assertStringNotContainsString('blog-wishlist-' . $deletedBlogId, $content);
    }

    public function testAccountOverviewHidesLinksForUnavailableBlogs(): void
    {
        $context = Context::createDefaultContext();
        $user = $this->createUser($context);
        $channelId = $this->getFrontendChannelId($context);

        $availableBlogId = $this->createBlog($context, 'Available blog', $channelId);
        $deactivatedBlogId = $this->createBlog($context, 'Deactivated blog', $channelId);

        $this->createOrderWithBlogs($context, $user, $channelId, [
            $availableBlogId => 'Available blog',
            $deactivatedBlogId => 'Deactivated blog',
        ]);

        static::getContainer()->get('blog.repository')->update([
            ['id' => $deactivatedBlogId, 'active' => false],
        ], $context);

        $browser = $this->login($user->getEmail());
        $browser->request('GET', '/account');

        $response = $browser->getResponse();
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);

        // the overview renders the newest order through the same template
        static::assertStringContainsString('title="Available blog"', $content);
        static::assertStringNotContainsString('title="Deactivated blog"', $content);
    }

    public function testReorderPostsTheOrderIdAndIsHiddenWhenNoBlogIsAvailable(): void
    {
        $context = Context::createDefaultContext();
        $user = $this->createUser($context);
        $channelId = $this->getFrontendChannelId($context);

        $availableBlogId = $this->createBlog($context, 'Available blog', $channelId);
        $reorderableOrderId = $this->createOrderWithBlogs($context, $user, $channelId, [
            $availableBlogId => 'Available blog',
        ]);

        $unavailableBlogId = $this->createBlog($context, 'Deactivated blog', $channelId);
        $unavailableOrderId = $this->createOrderWithBlogs($context, $user, $channelId, [
            $unavailableBlogId => 'Deactivated blog',
        ]);

        // an order that still has at least one buyable blog keeps its reorder entry
        $goneBlogId = $this->createBlog($context, 'Gone blog', $channelId);
        $partlyAvailableBlogId = $this->createBlog($context, 'Second blog', $channelId);
        $partlyAvailableOrderId = $this->createOrderWithBlogs($context, $user, $channelId, [
            $goneBlogId => 'Gone blog',
            $partlyAvailableBlogId => 'Second blog',
        ]);

        static::getContainer()->get('blog.repository')->update([
            ['id' => $unavailableBlogId, 'active' => false],
            ['id' => $goneBlogId, 'active' => false],
        ], $context);

        $browser = $this->login($user->getEmail());
        $browser->request('GET', '/account/order');

        $content = (string) $browser->getResponse()->getContent();

        // the form posts the order id instead of every line item
        static::assertStringContainsString('/checkout/cart/reorder/' . $reorderableOrderId, $content);
        // one unavailable blog does not remove the entry
        static::assertStringContainsString('/checkout/cart/reorder/' . $partlyAvailableOrderId, $content);
        // nothing buyable left, so no form at all
        static::assertStringNotContainsString('/checkout/cart/reorder/' . $unavailableOrderId, $content);
        // the route resolves the blogs from the order, so nothing is posted any more
        static::assertStringNotContainsString('lineItems[', $content);
    }

    public function testReorderAddsTheOrderBlogsToTheCartThroughTheFrontendRoute(): void
    {
        $context = Context::createDefaultContext();
        $user = $this->createUser($context);
        $channelId = $this->getFrontendChannelId($context);

        $availableBlogId = $this->createBlog($context, 'Available blog', $channelId);
        $deactivatedBlogId = $this->createBlog($context, 'Deactivated blog', $channelId);

        $orderId = $this->createOrderWithBlogs($context, $user, $channelId, [
            $availableBlogId => 'Available blog',
            $deactivatedBlogId => 'Deactivated blog',
        ]);

        static::getContainer()->get('blog.repository')->update([
            ['id' => $deactivatedBlogId, 'active' => false],
        ], $context);

        $browser = $this->login($user->getEmail());
        $browser->request('POST', '/checkout/cart/reorder/' . $orderId);

        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $browser->request('GET', '/checkout/cart');
        $cartContent = (string) $browser->getResponse()->getContent();

        // the deactivated blog was skipped by the cart pipeline
        static::assertStringContainsString($availableBlogId, $cartContent);
        static::assertStringNotContainsString($deactivatedBlogId, $cartContent);
    }

    public function testReorderOfAnotherUsersOrderIsRejected(): void
    {
        $context = Context::createDefaultContext();
        $user = $this->createUser($context);
        $channelId = $this->getFrontendChannelId($context);

        $blogId = $this->createBlog($context, 'Foreign blog', $channelId);

        $foreignUser = $this->createUser($context, false, Uuid::randomHex() . '@example.com');

        $foreignOrderId = $this->createOrderWithBlogs($context, $foreignUser, $channelId, [
            $blogId => 'Foreign blog',
        ]);

        $browser = $this->login($user->getEmail());
        $browser->request('POST', '/checkout/cart/reorder/' . $foreignOrderId);

        // not the logged-in user's order, so nothing is added
        $browser->request('GET', '/checkout/cart');
        static::assertStringNotContainsString($blogId, (string) $browser->getResponse()->getContent());
    }

    /**
     * @param array<string, string> $blogs blog id => label
     */
    private function createOrderWithBlogs(Context $context, UserEntity $user, string $channelId, array $blogs): string
    {
        $orderId = Uuid::randomHex();
        $orderData = $this->getOrderData($orderId, $context);
        $orderData[0]['orderUser']['user']['id'] = $user->getId();
        $orderData[0]['orderUser']['user']['email'] = $user->getEmail();
        $orderData[0]['orderUser']['user']['guest'] = false;
        $orderData[0]['channelId'] = $channelId;
        $orderData[0]['deepLinkCode'] = Uuid::randomHex();
        $orderData[0]['deliveries'] = [];

        $lineItems = [];
        foreach ($blogs as $blogId => $label) {
            $lineItems[] = [
                'id' => Uuid::randomHex(),
                'identifier' => $blogId,
                'referencedId' => $blogId,
                'blogId' => $blogId,
                'quantity' => 1,
                'type' => LineItem::BLOG_LINE_ITEM_TYPE,
                'label' => $label,
                'payload' => ['blogNumber' => $blogId],
                'price' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'priceDefinition' => new QuantityPriceDefinition(10, new TaxRuleCollection()),
                'priority' => 100,
                'good' => true,
                'position' => 1,
            ];
        }

        $orderData[0]['lineItems'] = $lineItems;

        static::getContainer()->get('order.repository')->create([$orderData[0]], $context);

        return $orderId;
    }

    private function login(string $email): KernelBrowser
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            $this->tokenize('frontend.account.login', [
                'username' => $email,
                'password' => 'contena',
            ])
        );
        $response = $browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        return $browser;
    }

    private function createUser(Context $context, bool $guest = false, ?string $email = null): UserEntity
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'channelId' => TestDefaults::CHANNEL,
            'boundChannelId' => null,
            'defaultShippingAddress' => [
                'id' => $addressId,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'guest' => $guest,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $email ?? 'test@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'userNumber' => $email === null ? '12345' : Uuid::randomHex(),
        ];

        /** @var EntityRepository<UserCollection> $repo */
        $repo = static::getContainer()->get('user.repository');
        $repo->create([$user], $context);

        /** @var UserEntity|null $user */
        $user = $repo->search(new Criteria([$userId]), $context)->getEntities()->first();

        static::assertNotNull($user);

        return $user;
    }

    private function createBlog(Context $context, string $name = 'Test Blog', ?string $channelId = null): string
    {
        $blogId = Uuid::randomHex();

        $blogNumber = Uuid::randomHex();
        $data = [
            'id' => $blogId,
            'blogNumber' => $blogNumber,
            'stock' => 1,
            'name' => $name,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10.99, 'net' => 11.99, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'taxId' => $this->getValidTaxId(),
            'active' => true,
            'visibilities' => [
                ['channelId' => $channelId ?? TestDefaults::CHANNEL, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];
        static::getContainer()->get('blog.repository')->create([$data], $context);

        return $blogId;
    }

    private function createAfterOrderPaymentMethod(Context $context, string $channelId, string $name): string
    {
        $paymentMethodId = Uuid::randomHex();

        static::getContainer()->get('payment_method.repository')->create([
            [
                'id' => $paymentMethodId,
                'name' => $name,
                'technicalName' => 'payment_test_' . $paymentMethodId,
                'active' => true,
                'afterOrderEnabled' => true,
                'channels' => [
                    ['id' => $channelId],
                ],
            ],
        ], $context);

        return $paymentMethodId;
    }

    private function createOrderWithTransaction(Context $context, UserEntity $user, string $channelId, string $paymentMethodId): string
    {
        $orderId = Uuid::randomHex();
        $transactionId = Uuid::randomHex();

        $orderData = $this->getOrderData($orderId, $context);
        $orderData[0]['orderUser']['user']['id'] = $user->getId();
        $orderData[0]['orderUser']['user']['guest'] = false;
        $orderData[0]['channelId'] = $channelId;
        $orderData[0]['primaryOrderTransactionId'] = $transactionId;
        $orderData[0]['transactions'] = [
            [
                'id' => $transactionId,
                'paymentMethodId' => $paymentMethodId,
                'stateId' => $this->getStateMachineState(OrderTransactionStates::STATE_MACHINE, OrderTransactionStates::STATE_OPEN),
                'amount' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            ],
        ];

        static::getContainer()->get('order.repository')->create([$orderData[0]], $context);

        return $orderId;
    }

    private function getFrontendChannelId(Context $context): string
    {
        $criteria = new Criteria();
        $criteria
            ->addFilter(new EqualsFilter('typeId', Defaults::CHANNEL_TYPE_FRONTEND))
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('domains.url', $_SERVER['APP_URL']));

        $channel = $this->channelRepository->search($criteria, $context)->getEntities()->first();
        static::assertNotNull($channel);

        return $channel->getId();
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartPersister;
use Contena\Core\Checkout\Cart\Error\Error;
use Contena\Core\Checkout\Cart\Error\ErrorCollection;
use Contena\Core\Checkout\Cart\Event\CartLoadedEvent;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Cart\Transaction\Struct\Transaction;
use Contena\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Contena\Core\Checkout\Order\Exception\PaymentMethodNotAvailableException;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Checkout\Order\OrderException;
use Contena\Core\Checkout\Order\Channel\OrderService;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Payment\PaymentMethodEntity;
use Contena\Core\Checkout\Promotion\Cart\Error\PromotionNotFoundError;
use Contena\Core\Checkout\Shipping\ShippingMethodCollection;
use Contena\Core\Checkout\Shipping\ShippingMethodEntity;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\Cart\BlogOutOfStockError;
use Contena\Core\Defaults;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Api\Util\AccessKeyHelper;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Debugging\ScriptTraces;
use Contena\Core\Framework\Test\Seo\FrontendChannelTestHelper;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\ChannelRequest;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Integration\PaymentHandler\TestPaymentHandler;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Checkout\Cart\Error\PaymentMethodChangedError;
use Contena\Frontend\Checkout\Cart\Error\ShippingMethodChangedError;
use Contena\Frontend\Controller\CheckoutController;
use Contena\Frontend\Framework\AffiliateTracking\AffiliateTrackingListener;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Page\Checkout\Cart\CheckoutCartPageLoadedHook;
use Contena\Frontend\Page\Checkout\Confirm\CheckoutConfirmPageLoadedHook;
use Contena\Frontend\Page\Checkout\Finish\CheckoutFinishPageLoadedHook;
use Contena\Frontend\Page\Checkout\Offcanvas\CheckoutInfoWidgetLoadedHook;
use Contena\Frontend\Page\Checkout\Offcanvas\CheckoutOffcanvasWidgetLoadedHook;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @internal
 */
class CheckoutControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use FrontendChannelTestHelper;

    private const UUID_LENGTH = 32;
    private const BLOG_PRICE = 15.99;
    private const CUSTOMER_NAME = 'Tester';
    private const TEST_AFFILIATE_CODE = 'testAffiliateCode';
    private const TEST_CAMPAIGN_CODE = 'testCampaignCode';
    private const SHIPPING_METHOD_BLOCKED_ERROR_CONTENT = 'The shipping method "%s" is blocked for your current shopping cart.';
    private const SHIPPING_METHOD_CHANGED_ERROR_CONTENT = '"%s" shipping is not available for your current cart, the shipping was changed to "%s".';
    private const PAYMENT_METHOD_BLOCKED_ERROR_CONTENT = 'The payment method "%s" is blocked for your current shopping cart.';
    private const PAYMENT_METHOD_CHANGED_ERROR_CONTENT = '"%s" payment is not available for your current cart, the payment was changed to "%s".';
    private const PROMOTION_NOT_FOUND_ERROR_CONTENT = 'Promo code "tn-08" could not be found.';
    private const BLOG_STOCK_REACHED_ERROR_CONTENT = 'The blog "Test blog" is not available any more';

    private ?string $userId = null;

    /**
     * @param string|float|int|bool|null $userComment
     */
    #[DataProvider('userComments')]
    public function testOrderUserComment($userComment, ?string $savedUserComment): void
    {
        $order = $this->performOrder($userComment);
        static::assertSame($savedUserComment, $order->getUserComment());
    }

    /**
     * @return array<mixed>
     */
    public static function userComments(): array
    {
        return [
            ["  Hello, \nthis is a user comment!  ", "Hello, \nthis is a user comment!"],
            ['<script>alert("hello")</script>', ''],
            ['<h1>Hello</h1><br><br>This is a Test! ', 'HelloThis is a Test!'],
            ['  ', null],
            ['', null],
            [1.2, '1.2'],
            [12, '12'],
            [true, '1'],
            [false, null],
            [null, null],
        ];
    }

    public function testOrder(): void
    {
        $order = $this->performOrder('');

        static::assertSame(self::BLOG_PRICE, $order->getPrice()->getTotalPrice());
        $orderUserEntity = $order->getOrderUser();
        static::assertNotNull($orderUserEntity);
        static::assertSame(self::CUSTOMER_NAME, $orderUserEntity->getLastName());
    }

    public function testOrderWithInactivePaymentMethod(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(PaymentMethodNotAvailableException::class);
        } else {
            $this->expectException(OrderException::class);
        }

        $this->performOrder('', false);
    }

    public function testOrderWithFailedPaymentMethod(): void
    {
        $contextToken = Uuid::randomHex();

        $cart = $this->fillCart($contextToken);

        $requestDataBag = $this->createRequestDataBag('');
        $channelContext = $this->createChannelContext($contextToken, $cart->getTransactions()->first()?->getPaymentMethodId());
        $request = $this->createRequest();
        $request->request->set('fail', true);

        /** @var RedirectResponse|Response $response */
        $response = static::getContainer()->get(CheckoutController::class)->order($requestDataBag, $channelContext, $request);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertStringContainsString('/account/order/edit', $response->getTargetUrl(), 'Target Url does not point to /checkout/finish');
    }

    public function testAffiliateAndCampaignTracking(): void
    {
        $request = $this->createRequest();
        $request->getSession()->set(AffiliateTrackingListener::AFFILIATE_CODE_KEY, self::TEST_AFFILIATE_CODE);
        $request->getSession()->set(AffiliateTrackingListener::CAMPAIGN_CODE_KEY, self::TEST_CAMPAIGN_CODE);

        $order = $this->performOrder('', true, $request);

        static::assertSame(self::TEST_AFFILIATE_CODE, $order->getAffiliateCode());
        static::assertSame(self::TEST_CAMPAIGN_CODE, $order->getCampaignCode());
    }

    public function testAffiliateTracking(): void
    {
        $request = $this->createRequest();
        $request->getSession()->set(AffiliateTrackingListener::CAMPAIGN_CODE_KEY, self::TEST_CAMPAIGN_CODE);

        $order = $this->performOrder('', true, $request);

        static::assertSame(self::TEST_CAMPAIGN_CODE, $order->getCampaignCode());
        static::assertNull($order->getAffiliateCode());
    }

    public function testCampaignOrderTracking(): void
    {
        $request = $this->createRequest();
        $request->getSession()->set(AffiliateTrackingListener::AFFILIATE_CODE_KEY, self::TEST_AFFILIATE_CODE);

        $order = $this->performOrder('', true, $request);

        static::assertSame(self::TEST_AFFILIATE_CODE, $order->getAffiliateCode());
        static::assertNull($order->getCampaignCode());
    }

    public function testOrderWithEmptyCartDoesNotResultIn400StatusCode(): void
    {
        $browser = $this->getBrowserWithLoggedInUser();

        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/checkout/order',
            [
                'tos' => 'on',
            ]
        );

        $response = $browser->getResponse();
        static::assertLessThan(400, $response->getStatusCode(), (string) $response->getContent());
    }

    /**
     * @param array<string> $errorKeys
     */
    #[DataProvider('errorDataProvider')]
    public function testOffCanvasWithErrorsFlash(ErrorCollection $errors, array $errorKeys, bool $testSwitchToDefault = false, bool $orderShouldBeBlocked = false): void
    {
        $browser = $this->getBrowserWithLoggedInUser();
        $browser->followRedirects(true);

        // Clear flashback from login and/or register
        /** @var Session $session */
        $session = $this->getSession();
        $session->getFlashBag()->clear();

        $browserChannelId = $browser->getServerParameter('test-sales-channel-id');

        $blogId = Uuid::randomHex();
        $this->createBlogOnDatabase($blogId, 'test.123', $browserChannelId);

        foreach ($errors as $error) {
            $this->prepareErrors(
                $error,
                $browser,
                $browserChannelId,
                $blogId,
                $testSwitchToDefault
            );
        }

        // Always add a blog to the cart
        $browser->request(
            'POST',
            '/checkout/blog/add-by-number',
            [
                'number' => 'test.123',
            ]
        );
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $browser->request(
            'GET',
            '/checkout/offcanvas'
        );
        $response = $browser->getResponse();
        $contentReturn = $response->getContent();
        static::assertNotFalse($contentReturn);

        $crawler = new Crawler();
        $crawler->addHtmlContent($contentReturn);
        $errorContent = $crawler->filterXPath('//div[@class="alert-content-container"]')->text();
        foreach ($errorKeys as $errorKey) {
            static::assertStringContainsString($errorKey, $errorContent);
        }
    }

    /**
     * @param array<string> $errorKeys
     */
    #[DataProvider('errorDataProvider')]
    public function testConfirmWithErrorsFlash(ErrorCollection $errors, array $errorKeys, bool $testSwitchToDefault = false, bool $orderShouldBeBlocked = false): void
    {
        $browser = $this->getBrowserWithLoggedInUser();
        $browser->followRedirects(true);
        $browserChannelId = $browser->getServerParameter('test-sales-channel-id');

        $blogId = Uuid::randomHex();
        $this->createBlogOnDatabase($blogId, 'test.123', $browserChannelId);

        $stockError = false;

        foreach ($errors as $error) {
            if ($error instanceof BlogOutOfStockError) {
                // If not redirected e.g. 'BlogOutOfStockError' does
                $stockError = true;
            }

            $this->prepareErrors(
                $error,
                $browser,
                $browserChannelId,
                $blogId,
                $testSwitchToDefault
            );
        }

        // Always add a blog to the cart
        $browser->request(
            'POST',
            '/checkout/blog/add-by-number',
            [
                'number' => 'test.123',
            ]
        );
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $browser->request(
            'GET',
            '/checkout/confirm'
        );
        $response = $browser->getResponse();
        $contentReturn = $response->getContent();
        static::assertNotFalse($contentReturn);

        $crawler = new Crawler();
        $crawler->addHtmlContent($contentReturn);

        $errorContent = implode('', $crawler->filterXPath('//div[@class="alert-content-container"]')->each(static fn (Crawler $node) => $node->text()));
        foreach ($errorKeys as $errorKey) {
            static::assertStringContainsString($errorKey, $errorContent);
        }
        if ($testSwitchToDefault) {
            $activeShippingMethod = $crawler->filterXPath('//div[contains(concat(" ",normalize-space(@class)," "), " shipping-method-radio ")][input/@checked]')->text();
            static::assertStringContainsString('Standard', $activeShippingMethod);

            $activePaymentMethod = $crawler->filterXPath('//div[contains(concat(" ",normalize-space(@class)," "), " payment-method-radio ")][input/@checked]')->text();
            static::assertStringContainsString('Paid in advance', $activePaymentMethod);
        }

        // Ensure submit order button is disabled
        if (!$stockError) {
            $submitButton = $crawler->filterXPath('//button[@id="confirmFormSubmit"][@disabled]');
            static::assertCount(($orderShouldBeBlocked || $errors->blockOrder()) ? 1 : 0, $submitButton);
        }
    }

    /**
     * @return iterable<array<mixed>>
     */
    public static function errorDataProvider(): iterable
    {
        /** @var EntityRepository<ShippingMethodCollection> */
        $shippingMethodRepository = static::getContainer()->get('shipping_method.repository');
        $shippingMethods = $shippingMethodRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();
        $standardShippingMethodId = $shippingMethods->filter(static fn (ShippingMethodEntity $sm) => $sm->getTechnicalName() === 'shipping_standard')->first()?->getId();
        $expressShippingMethodId = $shippingMethods->filter(static fn (ShippingMethodEntity $sm) => $sm->getTechnicalName() === 'shipping_express')->first()?->getId();
        static::assertNotNull($standardShippingMethodId, 'Standard shipping method not found');
        static::assertNotNull($expressShippingMethodId, 'Express shipping method not found');

        /** @var EntityRepository<PaymentMethodCollection> */
        $paymentMethodRepository = static::getContainer()->get('payment_method.repository');
        $paymentMethods = $paymentMethodRepository->search(new Criteria(), Context::createDefaultContext())->getEntities();
        $cashOnDeliveryPaymentMethodId = $paymentMethods->filter(static fn (PaymentMethodEntity $pm) => $pm->getTechnicalName() === 'payment_cashpayment')->first()?->getId();
        $paidInAdvancePaymentMethodId = $paymentMethods->filter(static fn (PaymentMethodEntity $pm) => $pm->getTechnicalName() === 'payment_prepayment')->first()?->getId();
        $invoicePaymentMethodId = $paymentMethods->filter(static fn (PaymentMethodEntity $pm) => $pm->getTechnicalName() === 'payment_invoicepayment')->first()?->getId();
        static::assertNotNull($cashOnDeliveryPaymentMethodId, 'Cash on delivery payment method not found');
        static::assertNotNull($paidInAdvancePaymentMethodId, 'Paid in advance payment method not found');
        static::assertNotNull($invoicePaymentMethodId, 'Invoice payment method not found');

        yield 'single blocked shipping method is switched to the fallback method' => [
            new ErrorCollection(
                [
                    new ShippingMethodChangedError(
                        oldShippingMethodId: $standardShippingMethodId,
                        oldShippingMethodName: 'Standard',
                        newShippingMethodId: $expressShippingMethodId,
                        newShippingMethodName: 'Express',
                        reason: 'foo',
                    ),
                ]
            ),
            [
                \sprintf(self::SHIPPING_METHOD_CHANGED_ERROR_CONTENT, 'Standard', 'Express'),
            ],
        ];
        yield 'blocked shipping method remains blocked when every shipping method is blocked' => [
            new ErrorCollection(
                [
                    new ShippingMethodChangedError(
                        oldShippingMethodId: $standardShippingMethodId,
                        oldShippingMethodName: 'Standard',
                        newShippingMethodId: $expressShippingMethodId,
                        newShippingMethodName: 'Express',
                        reason: 'foo',
                    ),
                    new ShippingMethodChangedError(
                        oldShippingMethodId: $expressShippingMethodId,
                        oldShippingMethodName: 'Express',
                        newShippingMethodId: $standardShippingMethodId,
                        newShippingMethodName: 'Standard',
                        reason: 'foo',
                    ),
                ]
            ),
            [
                \sprintf(self::SHIPPING_METHOD_BLOCKED_ERROR_CONTENT, 'Express'),
            ],
            false,
            true,
        ];
        yield 'single blocked payment method is switched to the fallback method' => [
            new ErrorCollection(
                [
                    new PaymentMethodChangedError(
                        oldPaymentMethodId: $cashOnDeliveryPaymentMethodId,
                        oldPaymentMethodName: 'Cash On Delivery',
                        newPaymentMethodId: $paidInAdvancePaymentMethodId,
                        newPaymentMethodName: 'Paid in advance',
                        reason: 'bar',
                    ),
                ]
            ),
            [
                \sprintf(self::PAYMENT_METHOD_CHANGED_ERROR_CONTENT, 'Cash on delivery', 'Paid in advance'),
            ],
        ];
        yield 'blocked payment method remains blocked when every payment method is blocked' => [
            new ErrorCollection(
                [
                    new PaymentMethodChangedError(
                        oldPaymentMethodId: $paidInAdvancePaymentMethodId,
                        oldPaymentMethodName: 'Paid in advance',
                        newPaymentMethodId: $invoicePaymentMethodId,
                        newPaymentMethodName: 'Invoice',
                        reason: 'bar',
                    ),
                    new PaymentMethodChangedError(
                        oldPaymentMethodId: $invoicePaymentMethodId,
                        oldPaymentMethodName: 'Invoice',
                        newPaymentMethodId: $cashOnDeliveryPaymentMethodId,
                        newPaymentMethodName: 'Cash On Delivery',
                        reason: 'bar',
                    ),
                    new PaymentMethodChangedError(
                        oldPaymentMethodId: $cashOnDeliveryPaymentMethodId,
                        oldPaymentMethodName: 'Cash On Delivery',
                        newPaymentMethodId: $paidInAdvancePaymentMethodId,
                        newPaymentMethodName: 'Paid in advance',
                        reason: 'bar',
                    ),
                ]
            ),
            [
                \sprintf(self::PAYMENT_METHOD_BLOCKED_ERROR_CONTENT, 'Cash on delivery'),
            ],
            false,
            true,
        ];
        yield 'blocked shipping and payment methods are both switched to fallbacks' => [
            new ErrorCollection(
                [
                    new ShippingMethodChangedError(
                        oldShippingMethodId: $standardShippingMethodId,
                        oldShippingMethodName: 'Standard',
                        newShippingMethodId: $expressShippingMethodId,
                        newShippingMethodName: 'Express',
                        reason: 'foo',
                    ),
                    new PaymentMethodChangedError(
                        oldPaymentMethodId: $cashOnDeliveryPaymentMethodId,
                        oldPaymentMethodName: 'Cash On Delivery',
                        newPaymentMethodId: $paidInAdvancePaymentMethodId,
                        newPaymentMethodName: 'Paid in advance',
                        reason: 'bar',
                    ),
                ]
            ),
            [
                \sprintf(self::SHIPPING_METHOD_CHANGED_ERROR_CONTENT, 'Standard', 'Express'),
                \sprintf(self::PAYMENT_METHOD_CHANGED_ERROR_CONTENT, 'Cash on delivery', 'Paid in advance'),
            ],
        ];
        yield 'non default shipping and payment methods are switched back to defaults' => [
            new ErrorCollection(
                [
                    new ShippingMethodChangedError(
                        oldShippingMethodId: $expressShippingMethodId,
                        oldShippingMethodName: 'Express',
                        newShippingMethodId: $standardShippingMethodId,
                        newShippingMethodName: 'Standard',
                        reason: 'foo',
                    ),
                    new PaymentMethodChangedError(
                        oldPaymentMethodId: $invoicePaymentMethodId,
                        oldPaymentMethodName: 'Invoice',
                        newPaymentMethodId: $paidInAdvancePaymentMethodId,
                        newPaymentMethodName: 'Paid in advance',
                        reason: 'bar',
                    ),
                ]
            ),
            [
                \sprintf(self::SHIPPING_METHOD_CHANGED_ERROR_CONTENT, 'Express', 'Standard'),
                \sprintf(self::PAYMENT_METHOD_CHANGED_ERROR_CONTENT, 'Invoice', 'Paid in advance'),
            ],
            true,
        ];
        yield 'error promotion not found' => [
            new ErrorCollection(
                [
                    new PromotionNotFoundError('tn-08'),
                ]
            ),
            [
                self::PROMOTION_NOT_FOUND_ERROR_CONTENT,
            ],
        ];
        yield 'error blog out of stock' => [
            new ErrorCollection(
                [
                    new BlogOutOfStockError('blog id', 'Car'),
                ]
            ),
            [
                self::BLOG_STOCK_REACHED_ERROR_CONTENT,
            ],
        ];
    }

    public function testCheckoutCartPageLoadedHookScriptsAreExecuted(): void
    {
        $browser = $this->getBrowserWithLoggedInUser();

        $browser->request(
            'GET',
            '/checkout/cart'
        );

        $traces = $browser->getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(CheckoutCartPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testCheckoutCartPageRendersOptionalCheckoutAssistForms(): void
    {
        $browser = $this->getBrowserWithLoggedInUser();
        $browserChannelId = $browser->getServerParameter('test-sales-channel-id');

        $blogId = Uuid::randomHex();
        $this->createBlogOnDatabase($blogId, 'test.123', $browserChannelId);

        $browser->request('POST', '/checkout/blog/add-by-number', ['number' => 'test.123']);
        $browser->request('GET', '/checkout/cart');

        $content = $browser->getResponse()->getContent();
        static::assertNotFalse($content);

        $crawler = new Crawler();
        $crawler->addHtmlContent($content);

        static::assertCount(1, $crawler->filterXPath('//label[@for="addBlogInput" and contains(concat(" ", normalize-space(@class), " "), " mb-1 ")]'));
        static::assertCount(1, $crawler->filterXPath('//input[@id="addBlogInput" and not(@required)]'));
        static::assertCount(1, $crawler->filterXPath('//button[@id="addBlogButton"]'));
        static::assertCount(1, $crawler->filterXPath('//input[@id="addPromotionInput" and not(@required)]'));
        static::assertCount(1, $crawler->filterXPath('//button[@id="addPromotion"]'));
    }

    public function testCartJsonLoadsTheCartOnce(): void
    {
        $browser = $this->getBrowserWithLoggedInUser();
        $browserChannelId = $browser->getServerParameter('test-sales-channel-id');

        $blogId = Uuid::randomHex();
        $this->createBlogOnDatabase($blogId, 'test.123', $browserChannelId);

        $browser->request('POST', '/checkout/blog/add-by-number', ['number' => 'test.123']);

        $loadedCarts = [];
        $tracker = static function (CartLoadedEvent $event) use (&$loadedCarts): void {
            $loadedCarts[] = $event->getCart()->getToken();
        };

        EventHookDispatcher::fromContainer(static::getContainer())->on(CartLoadedEvent::class, $tracker);

        $browser->request('GET', '/checkout/cart.json');

        static::assertCount(1, $loadedCarts);

        $response = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(1, $response['lineItems']);
        static::assertIsString($response['hash']);
        static::assertNotSame('', $response['hash']);
    }

    public function testCheckoutConfirmPageLoadedHookScriptsAreExecuted(): void
    {
        $contextToken = Uuid::randomHex();

        $cart = $this->fillCart($contextToken);
        $channelContext = $this->createChannelContext($contextToken, $cart->getTransactions()->first()?->getPaymentMethodId());
        static::getContainer()->get(CartPersister::class)->save($cart, $channelContext);

        $request = $this->createRequest($channelContext);

        static::getContainer()->get(CheckoutController::class)->confirmPage($request, $channelContext);

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(CheckoutConfirmPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testCheckoutConfirmPageConstraintViolationErrorWithInvalidAddress(): void
    {
        $contextToken = Uuid::randomHex();

        $cart = $this->fillCart($contextToken);
        $userId = $this->createUser();

        static::getContainer()->get(Connection::class)->executeStatement(
            'UPDATE `user_address` SET `city` = " " WHERE `user_id` = :userId',
            ['userId' => Uuid::fromHexToBytes($userId)]
        );

        $channelContext = $this->createChannelContext($contextToken, null, $userId);
        static::getContainer()->get(CartPersister::class)->save($cart, $channelContext);

        $request = $this->createRequest($channelContext);

        $response = static::getContainer()->get(CheckoutController::class)->confirmPage($request, $channelContext);
        $crawler = new Crawler();
        $crawler->addHtmlContent((string) $response->getContent());

        $translatedMessage = static::getContainer()->get('translator')->trans('checkout.billing-address-invalid', [
            '%url%' => static::getContainer()->get('router')->generate('frontend.account.address.edit.page', ['addressId' => $userId]),
        ]);
        $errorContent = $crawler->filterXPath('//div[@class="flashbags"]//div[@class="alert-content-container"]')->html();

        static::assertStringContainsString($translatedMessage, $errorContent);
    }

    public function testJsonCart(): void
    {
        $browser = $this->getBrowserWithLoggedInUser();
        $browserChannelId = $browser->getServerParameter('test-sales-channel-id');

        $blogId = Uuid::randomHex();
        $this->createBlogOnDatabase($blogId, 'test.123', $browserChannelId);

        // Always add a blog to the cart
        $browser->request(
            'POST',
            '/checkout/blog/add-by-number',
            [
                'number' => 'test.123',
            ]
        );

        $browser->request('GET', '/checkout/cart.json');

        $response = $browser->getResponse();

        static::assertSame(200, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);

        static::assertIsArray($content);
        static::assertArrayHasKey('price', $content);
        static::assertArrayHasKey('lineItems', $content);
        static::assertArrayHasKey('deliveries', $content);
        static::assertArrayHasKey('errors', $content);
        static::assertArrayHasKey('transactions', $content);
        static::assertCount(1, $content['lineItems']);
        static::assertArrayHasKey('id', $content['lineItems'][0]);
        static::assertArrayHasKey('type', $content['lineItems'][0]);
        static::assertArrayHasKey('label', $content['lineItems'][0]);
        static::assertArrayHasKey('quantity', $content['lineItems'][0]);
    }

    public function testCheckoutFinishPageLoadedHookScriptsAreExecuted(): void
    {
        $contextToken = Uuid::randomHex();

        $order = $this->performOrder('', true, null, $contextToken);

        $channelContext = $this->createChannelContext($contextToken);
        $request = $this->createRequest($channelContext);
        $request->query->set('orderId', $order->getId());
        $requestDataBag = $this->createRequestDataBag('');

        static::getContainer()->get(CheckoutController::class)->finishPage($request, $channelContext, $requestDataBag);

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(CheckoutFinishPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testCheckoutInfoWidget(): void
    {
        $contextToken = Uuid::randomHex();

        $cartService = static::getContainer()->get(CartService::class);
        $cart = $cartService->createNew($contextToken);

        $blogId = $this->createBlog();
        $channelContext = $this->createChannelContext($contextToken);

        $cart = $cartService->add(
            $cart,
            [new LineItem('lineItem1', LineItem::BLOG_LINE_ITEM_TYPE, $blogId)],
            $channelContext
        );

        $request = $this->createRequest($channelContext);

        $response = static::getContainer()->get(CheckoutController::class)->info($request, $channelContext);
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertStringContainsString((string) $cart->getPrice()->getTotalPrice(), (string) $response->getContent());

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(CheckoutInfoWidgetLoadedHook::HOOK_NAME, $traces);
    }

    public function testCheckoutInfoWidgetSkipsCalculationAndRenderIfCartIsEmpty(): void
    {
        $contextToken = Uuid::randomHex();

        $cartService = static::getContainer()->get(CartService::class);
        $cartService->createNew($contextToken);

        $channelContext = $this->createChannelContext($contextToken);
        $request = $this->createRequest($channelContext);

        $response = static::getContainer()->get(CheckoutController::class)->info($request, $channelContext);
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        static::assertSame('', $response->getContent());
    }

    public function testCheckoutOffcanvasRendersOptionalPromotionField(): void
    {
        $browser = $this->getBrowserWithLoggedInUser();
        $browserChannelId = $browser->getServerParameter('test-sales-channel-id');

        $blogId = Uuid::randomHex();
        $this->createBlogOnDatabase($blogId, 'test.123', $browserChannelId);

        $browser->request('POST', '/checkout/blog/add-by-number', ['number' => 'test.123']);
        $browser->request('GET', '/checkout/offcanvas');

        $content = $browser->getResponse()->getContent();
        static::assertNotFalse($content);

        $crawler = new Crawler();
        $crawler->addHtmlContent($content);

        static::assertCount(1, $crawler->filterXPath('//input[@id="addPromotionOffcanvasCartInput" and not(@required)]'));
        static::assertCount(1, $crawler->filterXPath('//button[@id="addPromotionOffcanvasCart"]'));
    }

    public function testCheckoutOffcanvasWidgetLoadedHookScriptsAreExecuted(): void
    {
        $contextToken = Uuid::randomHex();

        $cart = $this->fillCart($contextToken);

        $channelContext = $this->createChannelContext($contextToken, $cart->getTransactions()->first()?->getPaymentMethodId());
        $request = $this->createRequest($channelContext);

        static::getContainer()->get(CheckoutController::class)->offcanvas($request, $channelContext);

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();
        static::assertArrayHasKey(CheckoutOffcanvasWidgetLoadedHook::HOOK_NAME, $traces);
    }

    private function updateChannel(string $channelId): void
    {
        $snippetSetId = $this->getSnippetSetIdForLocale('en-GB');
        $paymentMethodId = $this->getValidPaymentMethodId();
        $shippingMethodId = $this->getValidShippingMethodId();
        $countryId = $this->getValidCountryId();

        $data = [
            'id' => $channelId,
            'name' => 'test',
            'typeId' => Defaults::CHANNEL_TYPE_FRONTEND,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => $snippetSetId,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $paymentMethodId,
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $shippingMethodId,
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $countryId,
            'countryVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'serviceCategoryVersionId' => Defaults::LIVE_VERSION,
            'footerCategoryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'paymentMethods' => [['id' => $paymentMethodId]],
            'shippingMethods' => [['id' => $shippingMethodId]],
            'countries' => [['id' => $countryId]],
            'userGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'domains' => [
                [
                    'id' => $channelId,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $snippetSetId,
                    'currencyId' => Defaults::CURRENCY,
                    'url' => 'http://test.123',
                ],
            ],
        ];
        static::getContainer()->get('channel.repository')->update([$data], Context::createDefaultContext());
    }

    private function createBlogOnDatabase(string $blogId, string $blogNumber, string $channelId): void
    {
        $taxId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $blog = [
            'id' => $blogId,
            'name' => 'Test blog',
            'blogNumber' => $blogNumber,
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15.99, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['id' => $taxId, 'name' => 'testTaxRate', 'taxRate' => 15],
            'categories' => [
                ['id' => $blogId, 'name' => 'Test category'],
            ],
            'visibilities' => [
                [
                    'id' => $blogId,
                    'channelId' => $channelId,
                    'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];
        static::getContainer()->get('blog.repository')->create([$blog], $context);
    }

    /**
     * @param string|float|int|bool|null $userComment
     */
    private function performOrder($userComment, bool $paymentMethodActive = true, ?Request $request = null, ?string $contextToken = null): OrderEntity
    {
        if (!$contextToken) {
            $contextToken = Uuid::randomHex();
        }

        $cart = $this->fillCart($contextToken, $paymentMethodActive);

        $requestDataBag = $this->createRequestDataBag($userComment);
        $channelContext = $this->createChannelContext($contextToken, $cart->getTransactions()->first()?->getPaymentMethodId());
        if (!$request instanceof Request) {
            $request = $this->createRequest();
        }

        /** @var RedirectResponse|Response $response */
        $response = static::getContainer()->get(CheckoutController::class)->order($requestDataBag, $channelContext, $request);

        static::assertInstanceOf(RedirectResponse::class, $response);

        $orderId = mb_substr($response->getTargetUrl(), -self::UUID_LENGTH);

        /** @var EntityRepository<OrderCollection> $orderRepo */
        $orderRepo = static::getContainer()->get('order.repository');

        $order = $orderRepo->search(new Criteria([$orderId]), Context::createDefaultContext())->getEntities()->first();

        static::assertNotNull($order);

        return $order;
    }

    private function createUser(): string
    {
        if ($this->userId) {
            return $this->userId;
        }

        $this->userId = Uuid::randomHex();
        $salutationId = $this->getValidSalutationId();

        $user = [
            'id' => $this->userId,
            'channelId' => TestDefaults::CHANNEL,
            'defaultShippingAddress' => [
                'id' => $this->userId,
                'firstName' => 'Test',
                'lastName' => self::CUSTOMER_NAME,
                'city' => 'Schöppingen',
                'street' => 'Ebbinghoff 10',
                'zipcode' => '48624',
                'salutationId' => $salutationId,
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $this->userId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'not12345',
            'firstName' => 'Test',
            'lastName' => self::CUSTOMER_NAME,
            'salutationId' => $salutationId,
            'userNumber' => '12345',
        ];

        static::getContainer()->get('user.repository')->create([$user], Context::createDefaultContext());

        return $this->userId;
    }

    private function createBlog(): string
    {
        $blogId = Uuid::randomHex();

        $blog = [
            'id' => $blogId,
            'name' => 'Test blog',
            'blogNumber' => '123456789',
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => self::BLOG_PRICE, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['id' => $blogId, 'name' => 'Contena'],
            'tax' => ['id' => $blogId, 'name' => 'testTaxRate', 'taxRate' => 15],
            'categories' => [
                ['id' => $blogId, 'name' => 'Test category'],
            ],
            'visibilities' => [
                [
                    'id' => $blogId,
                    'channelId' => TestDefaults::CHANNEL,
                    'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        static::getContainer()->get('blog.repository')->create([$blog], Context::createDefaultContext());

        return $blogId;
    }

    private function fillCart(string $contextToken, bool $paymentMethodActive = true): Cart
    {
        $cart = static::getContainer()->get(CartService::class)->createNew($contextToken);

        $blogId = $this->createBlog();
        $cart->add(new LineItem('lineItem1', LineItem::BLOG_LINE_ITEM_TYPE, $blogId));

        $cart->setTransactions($this->createTransaction($paymentMethodActive));

        return $cart;
    }

    private function createTransaction(bool $active = true): TransactionCollection
    {
        $paymentMethodId = Uuid::randomHex();

        static::getContainer()->get('payment_method.repository')->upsert([[
            'id' => $paymentMethodId,
            'handlerIdentifier' => TestPaymentHandler::class,
            'name' => 'Test Payment',
            'technicalName' => 'payment_test',
            'description' => 'Test payment handler',
            'channels' => [
                [
                    'id' => TestDefaults::CHANNEL,
                ],
            ],
            'active' => $active,
        ]], Context::createDefaultContext());

        return new TransactionCollection([
            new Transaction(
                new CalculatedPrice(
                    13.37,
                    13.37,
                    new CalculatedTaxCollection(),
                    new TaxRuleCollection()
                ),
                $paymentMethodId
            ),
        ]);
    }

    /**
     * @param string|float|int|bool|null $userComment
     */
    private function createRequestDataBag($userComment): RequestDataBag
    {
        return new RequestDataBag(['tos' => true, OrderService::CUSTOMER_COMMENT_KEY => $userComment]);
    }

    private function createChannelContext(string $contextToken, ?string $paymentMethodId = null, ?string $userId = null): ChannelContext
    {
        $this->updateChannel(TestDefaults::CHANNEL);
        $channelData = [
            ChannelContextService::CUSTOMER_ID => $userId ?? $this->createUser(),
        ];
        if ($paymentMethodId !== null) {
            $channelData[ChannelContextService::PAYMENT_METHOD_ID] = $paymentMethodId;
        }

        return static::getContainer()->get(ChannelContextFactory::class)->create(
            $contextToken,
            TestDefaults::CHANNEL,
            $channelData
        );
    }

    private function createRequest(?ChannelContext $context = null): Request
    {
        $request = Request::create((string) EnvironmentHelper::getVariable('APP_URL'));
        $request->setSession($this->getSession());

        $request->attributes->add([
            RequestTransformer::FRONTEND_URL => EnvironmentHelper::getVariable('APP_URL'),
            ChannelRequest::ATTRIBUTE_IS_CHANNEL_REQUEST => true,
        ]);

        if ($context instanceof ChannelContext) {
            $request->attributes->add([
                PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT => $context,
            ]);
        }

        $request->request->set('noredirect', true);

        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push($request);

        return $request;
    }

    /**
     * Prepares all conditions for the given error to occur on a cart page visit.
     */
    private function prepareErrors(
        Error $error,
        KernelBrowser $browser,
        string $channelId,
        string $blogId,
        bool $shouldSwitchToDefault
    ): void {
        $availabilityRuleId = $this->createAvailabilityRule($channelId);
        $channelRepository = static::getContainer()->get('channel.repository');

        if ($error instanceof ShippingMethodChangedError) {
            $shippingMethodRepository = static::getContainer()->get('shipping_method.repository');
            $blockedId = $error->getOldShippingMethodId();
            $newId = $error->getNewShippingMethodId();

            $shippingMethodRepository->update([
                [
                    'id' => $blockedId,
                    'availabilityRuleId' => $availabilityRuleId,
                ],
            ], Context::createDefaultContext());

            $channelRepository->update([
                [
                    'id' => $channelId,
                    'shippingMethodId' => $shouldSwitchToDefault ? $newId : $blockedId,
                ],
            ], Context::createDefaultContext());

            if ($shouldSwitchToDefault) {
                $browser->request(
                    'POST',
                    '/checkout/configure',
                    [
                        ChannelContextService::SHIPPING_METHOD_ID => $blockedId,
                    ]
                );
            }

            return;
        }

        if ($error instanceof PaymentMethodChangedError) {
            $paymentMethodRepository = static::getContainer()->get('payment_method.repository');
            $blockedId = $error->getOldPaymentMethodId();
            $newId = $error->getNewPaymentMethodId();

            $paymentMethodRepository->update([
                [
                    'id' => $blockedId,
                    'availabilityRuleId' => $availabilityRuleId,
                ],
            ], Context::createDefaultContext());

            $channelRepository->update([
                [
                    'id' => $channelId,
                    'paymentMethodId' => $shouldSwitchToDefault ? $newId : $blockedId,
                ],
            ], Context::createDefaultContext());

            if ($shouldSwitchToDefault) {
                $browser->request(
                    'POST',
                    '/checkout/configure',
                    [
                        ChannelContextService::PAYMENT_METHOD_ID => $blockedId,
                    ]
                );
            }

            return;
        }

        if ($error instanceof PromotionNotFoundError) {
            $browser->request(
                'POST',
                '/checkout/promotion/add',
                [
                    'code' => $error->getParameters()['code'],
                ]
            );

            return;
        }

        if ($error instanceof BlogOutOfStockError) {
            $blogRepository = static::getContainer()->get('blog.repository');
            $blogRepository->update([
                [
                    'id' => $blogId,
                    'isCloseout' => true,
                    'stock' => 0,
                ],
            ], Context::createDefaultContext());

            return;
        }

        static::fail(\sprintf('Could not provoke error of type %s. Did you forget to implement it?', $error::class));
    }

    private function createAvailabilityRule(string $channelId): string
    {
        $ruleRepository = static::getContainer()->get('rule.repository');
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('name', 'NotAvailableWithTestChannel')
        );
        $ruleId = $ruleRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
        if ($ruleId !== null) {
            return $ruleId;
        }

        $ruleId = Uuid::randomHex();
        $orContainerId = Uuid::randomHex();
        $andContainerId = Uuid::randomHex();
        $ruleRepository->create([
            [
                'id' => $ruleId,
                'name' => 'NotAvailableWithTestDefaultChannel',
                'priority' => 1,
                'conditions' => [
                    [
                        'id' => $orContainerId,
                        'type' => 'orContainer',
                    ],
                    [
                        'id' => $andContainerId,
                        'type' => 'andContainer',
                        'parentId' => $orContainerId,
                    ],
                    [
                        'type' => 'channel',
                        'parentId' => $andContainerId,
                        'value' => [
                            'operator' => '!=',
                            'channelIds' => [
                                $channelId,
                            ],
                        ],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        return $ruleId;
    }
}

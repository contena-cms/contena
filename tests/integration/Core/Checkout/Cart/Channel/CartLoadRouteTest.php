<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Channel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\AbstractCartPersister;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartPersister;
use Contena\Core\Checkout\Cart\Event\CartLoadedEvent;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Contena\Core\Checkout\Cart\Rule\CartAmountRule;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Util\Base64;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Group('store-api')]
class CartLoadRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    /**
     * @var EntityRepository<PaymentMethodCollection>
     */
    private EntityRepository $paymentMethodRepository;

    private AbstractChannelContextFactory $channelFactory;

    private AbstractCartPersister $cartPersister;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->paymentMethodRepository = static::getContainer()->get('payment_method.repository');
        $this->cartPersister = static::getContainer()->get(CartPersister::class);
        $this->channelFactory = static::getContainer()->get(ChannelContextFactory::class);
    }

    public function testEmptyCart(): void
    {
        $this->browser
            ->request(
                'GET',
                '/store-api/checkout/cart',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(0, $response['price']['totalPrice']);
        static::assertCount(0, $response['errors']);
    }

    public function testIncludesOfTheCompressedCriteriaAreApplied(): void
    {
        $compressed = gzencode(json_encode(['includes' => ['cart' => ['token']]], \JSON_THROW_ON_ERROR));
        static::assertNotFalse($compressed, 'Gzip compressing failed');

        $this->browser->request('GET', '/store-api/checkout/cart', ['_criteria' => Base64::urlEncode($compressed)]);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('token', $response);
        static::assertArrayNotHasKey('price', $response, 'The includes of the compressed criteria are applied');
    }

    /**
     * @param array<string, string|array<string, string>>|null $ruleConditions
     */
    #[DataProvider('dataProviderPaymentMethodRule')]
    public function testFilledCart(?array $ruleConditions, int $errorCount): void
    {
        $context = $this->createPersistedCartWithBlog();

        if ($ruleConditions !== null) {
            $this->paymentMethodRepository->update([[
                'id' => $context->getPaymentMethod()->getId(),
                'availabilityRule' => [
                    'name' => 'Test Rule',
                    'priority' => 0,
                    'conditions' => [
                        $ruleConditions,
                    ],
                ],
            ]], Context::createDefaultContext());
        }

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->get('token'));

        $this->browser
            ->request(
                'GET',
                '/store-api/checkout/cart',
                [
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(10, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);
        static::assertSame('Test', $response['lineItems'][0]['label']);
        static::assertCount($errorCount, $response['errors']);
    }

    /**
     * @return array<string, array<int|array<string, string|array<string, string>>|null>>
     */
    public static function dataProviderPaymentMethodRule(): array
    {
        return [
            'No Rule' => [
                null,
                0,
            ],
            'Matching Rule' => [
                ['type' => (new AlwaysValidRule())->getName()],
                0,
            ],
            'Not Matching Rule' => [
                [
                    'type' => (new CartAmountRule())->getName(),
                    'value' => [
                        'operator' => Rule::OPERATOR_EQ,
                        'amount' => '-1.0',
                    ],
                ],
                1,
            ],
        ];
    }

    public function testCartIsLoadedOnceForTheContextToken(): void
    {
        $this->createPersistedCartWithBlog();

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->get('token'));

        $loadedCarts = [];
        $tracker = static function (CartLoadedEvent $event) use (&$loadedCarts): void {
            $loadedCarts[] = $event->getCart()->getToken();
        };

        EventHookDispatcher::fromContainer(static::getContainer())->on(CartLoadedEvent::class, $tracker);

        $this->browser->request('GET', '/store-api/checkout/cart');

        static::assertSame([$this->ids->get('token')], $loadedCarts);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(10, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);
        static::assertIsString($response['hash']);
        static::assertNotSame('', $response['hash']);
    }

    public function testDeferredCartErrors(): void
    {
        Feature::skipTestIfInActive('DEFERRED_CART_ERRORS', $this);

        $this->blogRepository->create([
            [
                'id' => $this->ids->create('blogId'),
                'blogNumber' => $this->ids->create('blogNumber'),
                'stock' => 1,
                'isCloseout' => true,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->ids->create('manufacturerId'), 'name' => 'test'],
                'tax' => ['id' => $this->ids->create('tax'), 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['channelId' => $this->ids->get('sales-channel'), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], Context::createDefaultContext());

        $this->login($this->browser);

        // Add blog to cart
        $this->browser->request(
            'POST',
            '/store-api/checkout/cart/line-item',
            [
                'items' => [
                    ['type' => LineItem::BLOG_LINE_ITEM_TYPE, 'referencedId' => $this->ids->get('blogId')],
                ],
            ],
        );

        // Check that blog was added to cart
        $cartResponse = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $cartResponse['lineItems']);
        static::assertCount(0, $cartResponse['errors']);

        // Set blog out of stock (to force a temporary cart error)
        $this->blogRepository->update([
            [
                'id' => $this->ids->create('blogId'),
                'stock' => 0,
            ],
        ], Context::createDefaultContext());

        // Fetch context to simulate a non cart related request
        $this->browser->request('GET', '/store-api/context');
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        // Fetch cart, make sure the blog is no longer in cart and an error is set
        $this->browser->request('GET', '/store-api/checkout/cart');
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $cartResponse = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(0, $cartResponse['lineItems']);
        static::assertCount(1, $cartResponse['errors']);

        // Fetch cart again, error should not be present anymore
        $this->browser->request('GET', '/store-api/checkout/cart');
        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $cartResponse = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(0, $cartResponse['lineItems']);
        static::assertCount(0, $cartResponse['errors']);
    }

    private function createPersistedCartWithBlog(): ChannelContext
    {
        $this->blogRepository->create([
            [
                'id' => $this->ids->create('blogId'),
                'blogNumber' => $this->ids->create('blogNumber'),
                'stock' => 1,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->ids->create('manufacturerId'), 'name' => 'test'],
                'tax' => ['id' => $this->ids->create('tax'), 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['channelId' => $this->ids->get('sales-channel'), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], Context::createDefaultContext());

        $cart = new Cart($this->ids->create('token'));
        $cart->add(new LineItem($this->ids->create('blogId'), LineItem::BLOG_LINE_ITEM_TYPE, $this->ids->get('blogId')));

        $context = $this->channelFactory->create($this->ids->get('token'), $this->ids->get('sales-channel'));
        $this->cartPersister->save($cart, $context);

        return $context;
    }
}

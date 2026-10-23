<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Checkout\Cart\Channel;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartCalculator;
use Contena\Core\Checkout\Cart\CartPersister;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItemFactoryRegistry;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Gateway\Channel\AbstractCheckoutGatewayRoute;
use Contena\Core\Checkout\Gateway\Channel\CheckoutGatewayRouteResponse;
use Contena\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Payment\PaymentMethodEntity;
use Contena\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Contena\Core\Checkout\Shipping\ShippingMethodCollection;
use Contena\Core\Checkout\Shipping\ShippingMethodEntity;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Channel\Channel\ContextSwitchRoute;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Checkout\Cart\Error\PaymentMethodChangedError;
use Contena\Frontend\Checkout\Cart\Error\ShippingMethodChangedError;
use Contena\Frontend\Checkout\Cart\Channel\FrontendCartFacade;
use Contena\Frontend\Checkout\Payment\BlockedPaymentMethodSwitcher;
use Contena\Frontend\Checkout\Shipping\BlockedShippingMethodSwitcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class FrontendCartFacadeTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private ChannelContext $context;

    private CartService $cartService;

    private FrontendCartFacade $cartFacade;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->cartService = $this->getContainer()->get(CartService::class);
        $this->cartFacade = $this->getContainer()->get(FrontendCartFacade::class);
    }

    public function testBlockedShippingMethodMessage(): void
    {
        $id = $this->changeShippingMethodAvailabilityRuleId();

        static::getContainer()->get(ChannelContextPersister::class)
            ->save($this->ids->get('token'), ['shippingMethodId' => $id], TestDefaults::CHANNEL);

        $this->context = self::getContainer()
            ->get(ChannelContextService::class)
            ->get(new ChannelContextServiceParameters(TestDefaults::CHANNEL, $this->ids->get('token')));

        $cart = new Cart($this->ids->get('token'));
        $cart = $this->cartService->add($cart, $this->createBlog(), $this->context);

        static::assertInstanceOf(ShippingMethodBlockedError::class, $cart->getErrors()->first());

        $result = $this->cartFacade->get($this->ids->get('token'), $this->context);

        static::assertCount(1, $result->getErrors());

        $error = $result->getErrors()->first();

        static::assertInstanceOf(ShippingMethodChangedError::class, $error);
        static::assertSame('Standard', $error->getOldShippingMethodName());
        static::assertSame('Express', $error->getNewShippingMethodName());

        $this->context = self::getContainer()
            ->get(ChannelContextService::class)
            ->get(new ChannelContextServiceParameters(TestDefaults::CHANNEL, $this->ids->get('token')));

        static::assertSame('Express', $this->context->getShippingMethod()->getName());
    }

    public function testBlockedPaymentMethodMessage(): void
    {
        $this->changePaymentMethodAvailabilityRuleId();

        $this->context = self::getContainer()
            ->get(ChannelContextService::class)
            ->get(new ChannelContextServiceParameters(TestDefaults::CHANNEL, $this->ids->get('token')));

        static::assertSame('Cash on delivery', $this->context->getPaymentMethod()->getName());

        $cart = new Cart($this->ids->get('token'));
        $cart = $this->cartService->add($cart, $this->createBlog(), $this->context);

        static::assertInstanceOf(PaymentMethodBlockedError::class, $cart->getErrors()->first());

        $result = $this->cartFacade->get($this->ids->get('token'), $this->context);

        static::assertCount(1, $result->getErrors());

        $error = $result->getErrors()->first();

        static::assertInstanceOf(PaymentMethodChangedError::class, $error);
        static::assertSame('Cash on delivery', $error->getOldPaymentMethodName());
        static::assertSame('Paid in advance', $error->getNewPaymentMethodName());

        $this->context = self::getContainer()
            ->get(ChannelContextService::class)
            ->get(new ChannelContextServiceParameters(TestDefaults::CHANNEL, $this->ids->get('token')));

        static::assertSame('Paid in advance', $this->context->getPaymentMethod()->getName());
    }

    public function testCheckoutGatewayBlockedPaymentMethodSwitchesToDefaultPaymentMethod(): void
    {
        $cashOnDeliveryPaymentMethod = $this->getPaymentMethodByTechnicalName('payment_cashpayment');
        $paidInAdvancePaymentMethod = $this->getPaymentMethodByTechnicalName('payment_prepayment');

        static::getContainer()->get('channel.repository')->update([
            [
                'id' => TestDefaults::CHANNEL,
                'paymentMethodId' => $paidInAdvancePaymentMethod->getId(),
            ],
        ], Context::createDefaultContext());

        static::getContainer()->get(ChannelContextPersister::class)
            ->save($this->ids->get('token'), ['paymentMethodId' => $cashOnDeliveryPaymentMethod->getId()], TestDefaults::CHANNEL);

        $this->context = self::getContainer()
            ->get(ChannelContextService::class)
            ->get(new ChannelContextServiceParameters(TestDefaults::CHANNEL, $this->ids->get('token')));

        static::assertSame($cashOnDeliveryPaymentMethod->getId(), $this->context->getPaymentMethod()->getId());

        $cart = new Cart($this->ids->get('token'));
        $this->cartService->add($cart, $this->createBlog(), $this->context);

        $checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $checkoutGatewayRoute
            ->expects($this->once())
            ->method('load')
            ->willReturnCallback(static function (Request $request, Cart $cart, ChannelContext $context) use ($cashOnDeliveryPaymentMethod, $paidInAdvancePaymentMethod): CheckoutGatewayRouteResponse {
                $cart->getErrors()->add(new PaymentMethodBlockedError(
                    id: $cashOnDeliveryPaymentMethod->getId(),
                    name: (string) $cashOnDeliveryPaymentMethod->getTranslation('name'),
                    reason: 'not allowed',
                ));

                return new CheckoutGatewayRouteResponse(
                    new PaymentMethodCollection([$paidInAdvancePaymentMethod]),
                    new ShippingMethodCollection([$context->getShippingMethod()]),
                    $cart->getErrors(),
                );
            });

        $cartFacade = new FrontendCartFacade(
            $this->cartService,
            static::getContainer()->get(BlockedShippingMethodSwitcher::class),
            static::getContainer()->get(BlockedPaymentMethodSwitcher::class),
            static::getContainer()->get(ContextSwitchRoute::class),
            static::getContainer()->get(CartCalculator::class),
            static::getContainer()->get(CartPersister::class),
            $checkoutGatewayRoute,
        );

        $result = $cartFacade->getWithCheckoutGateway(new Request(), $this->ids->get('token'), $this->context);

        static::assertSame($paidInAdvancePaymentMethod->getId(), $this->context->getPaymentMethod()->getId());
        static::assertCount(0, $result->cart->getErrors()->filterInstance(PaymentMethodBlockedError::class));
        static::assertCount(1, $result->cart->getErrors()->filterInstance(PaymentMethodChangedError::class));

        $persistedContext = self::getContainer()
            ->get(ChannelContextService::class)
            ->get(new ChannelContextServiceParameters(TestDefaults::CHANNEL, $this->ids->get('token')));

        static::assertSame($paidInAdvancePaymentMethod->getId(), $persistedContext->getPaymentMethod()->getId());
    }

    private function changeShippingMethodAvailabilityRuleId(): string
    {
        $ruleId = $this->createRule();

        $shippingMethodeRepository = $this->getContainer()->get('shipping_method.repository');

        $criteria = (new Criteria())->addFilter(new EqualsFilter('name', 'Standard'));
        $shippingMethod = $shippingMethodeRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(ShippingMethodEntity::class, $shippingMethod);

        $shippingMethodeRepository->update([['id' => $shippingMethod->getId(), 'availabilityRuleId' => $ruleId]], Context::createDefaultContext());

        return $shippingMethod->getId();
    }

    private function changePaymentMethodAvailabilityRuleId(): string
    {
        $ruleId = $this->createRule();

        $paymentMethodRepository = $this->getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())->addFilter(new EqualsFilter('handlerIdentifier', 'Contena\Core\Checkout\Payment\Cart\PaymentHandler\CashPayment'));
        $paymentMethod = $paymentMethodRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(PaymentMethodEntity::class, $paymentMethod);

        $paymentMethodRepository->update([['id' => $paymentMethod->getId(), 'availabilityRuleId' => $ruleId]], Context::createDefaultContext());

        return $paymentMethod->getId();
    }

    private function getPaymentMethodByTechnicalName(string $technicalName): PaymentMethodEntity
    {
        $paymentMethodRepository = $this->getContainer()->get('payment_method.repository');

        $criteria = (new Criteria())->addFilter(new EqualsFilter('technicalName', $technicalName));
        $paymentMethod = $paymentMethodRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(PaymentMethodEntity::class, $paymentMethod);

        return $paymentMethod;
    }

    private function createBlog(): LineItem
    {
        $blogRepository = $this->getContainer()->get('blog.repository');

        $standardTaxId = static::getContainer()->get('tax.repository')
            ->searchIds((new Criteria())->addFilter(new EqualsFilter('taxRate', 19.0)), Context::createDefaultContext())->firstId();

        static::assertNotNull($standardTaxId);

        $this->ids->set('tax-id', $standardTaxId);

        $blog = new BlogBuilder($this->ids, 'blog-1');
        $blog->price(10);
        $blog->visibility();
        $blog->tax('tax-id');

        $blogRepository->create([$blog->build()], Context::createDefaultContext());

        $blogFactory = $this->getContainer()->get(LineItemFactoryRegistry::class);

        return $blogFactory->create(
            ['type' => 'blog', 'id' => $this->ids->get('blog-1'), 'referencedId' => $this->ids->get('blog-1')],
            $this->context
        );
    }

    private function createRule(): string
    {
        $ruleId = Uuid::randomHex();

        $this->getContainer()->get('rule.repository')->upsert([[
            'id' => $ruleId,
            'name' => 'Test rule',
            'priority' => 1,
            'conditions' => [[
                'type' => 'cartCartAmount',
                'value' => [
                    'amount' => 120,
                    'operator' => '>',
                ],
            ]],
        ]], Context::createDefaultContext());

        return $ruleId;
    }
}

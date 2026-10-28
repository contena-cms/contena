<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Storefront\Page\Account;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Contena\Core\Checkout\Order\OrderCollection;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Checkout\Order\OrderException;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Payment\PaymentMethodEntity;
use Contena\Core\Content\Rule\RuleCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Contena\Core\System\StateMachine\StateMachineRegistry;
use Contena\Core\System\StateMachine\Transition;
use Contena\Storefront\Page\Account\Order\AccountEditOrderPageLoadedEvent;
use Contena\Storefront\Page\Account\Order\AccountEditOrderPageLoader;
use Contena\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class EditOrderPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    /**
     * @var EntityRepository<PaymentMethodCollection>
     */
    private EntityRepository $paymentMethodRepository;

    protected function setUp(): void
    {
        $this->paymentMethodRepository = static::getContainer()->get('payment_method.repository');
    }

    public function testEditOrderPageShouldLoad(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();
        $orderId = $this->placeRandomOrder($context);
        $request->attributes->set('orderId', $orderId);

        $event = null;
        $this->catchEvent(AccountEditOrderPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        self::assertPageEvent(AccountEditOrderPageLoadedEvent::class, $event, $context, $request, $page);

        static::assertSame($orderId, $page->getOrder()->getId());
    }

    public function testEditOrderPageCorrectPayment(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();
        $orderId = $this->placeRandomOrder($context);
        $request->attributes->set('orderId', $orderId);

        $event = null;
        $this->catchEvent(AccountEditOrderPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        self::assertPageEvent(AccountEditOrderPageLoadedEvent::class, $event, $context, $request, $page);

        static::assertCount(1, $page->getPaymentMethods());

        // set Payment active to false and assert it will not be loaded
        $criteria = (new Criteria())->addFilter(new EqualsFilter('active', true));
        $paymentMethod = $this->paymentMethodRepository->search($criteria, $context->getContext())->getEntities()->first();
        static::assertNotNull($paymentMethod);

        $this->paymentMethodRepository->update(
            [
                ['id' => $paymentMethod->getId(), 'active' => false],
            ],
            $context->getContext()
        );

        $request->attributes->set('orderId', $orderId);
        $page = $this->getPageLoader()->load($request, $context);

        self::assertPageEvent(AccountEditOrderPageLoadedEvent::class, $event, $context, $request, $page);

        static::assertCount(0, $page->getPaymentMethods());
    }

    public function testEditOrderPageSelectsThePaymentMethodOfTheOrder(): void
    {
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();
        $orderId = $this->placeRandomOrder($context);
        $orderPaymentMethodId = $context->getPaymentMethod()->getId();

        // the customer selected another payment method after the order was placed
        $context->assign(['paymentMethod' => $this->createCustomPaymentMethod($context, [])]);

        $request = new Request();
        $request->attributes->set('orderId', $orderId);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame($orderPaymentMethodId, $page->getSelectedPaymentMethodId());
    }

    public function testEditPageNotAvailableOrderIsPaid(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();
        $orderId = $this->placeRandomOrder($context);
        $request->attributes->set('orderId', $orderId);

        $this->setOrderToTransactionState($orderId, $context, StateMachineTransitionActions::ACTION_PAID);

        $this->expectException(OrderException::class);

        $this->getPageLoader()->load($request, $context);
    }

    public function testShouldOnlyShowAvailablePaymentsForOrder(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();
        $orderId = $this->placeRandomOrder($context);
        $request->attributes->set('orderId', $orderId);

        // Get digital products rule
        $ruleCriteria = new Criteria();
        $ruleCriteria->addFilter(new EqualsFilter('name', 'Shopping cart / Order with digital products'));

        /** @var EntityRepository<RuleCollection> $ruleRepository */
        $ruleRepository = static::getContainer()->get('rule.repository');

        $ruleId = $ruleRepository->search($ruleCriteria, $context->getContext())->getEntities()->first()?->getId();
        static::assertNotNull($ruleId);

        $this->createCustomPaymentWithRule($context, $ruleId);

        // Fake context rules for USA customers
        $context->setRuleIds(array_merge($context->getRuleIds(), [$ruleId]));

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame($orderId, $page->getOrder()->getId());
        static::assertCount(1, $page->getPaymentMethods());
    }

    public function testShouldSortAvailablePaymentMethodsByPreference(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();
        $orderId = $this->placeRandomOrder($context);
        $request->attributes->set('orderId', $orderId);

        $primaryMethod = $this->createCustomPaymentMethod($context, ['position' => 1]);

        // create some dummy methods to test sorting
        $this->createCustomPaymentMethod($context, ['position' => 0]);
        $this->createCustomPaymentMethod($context, ['position' => 4]);

        $context->getSalesChannel()->setPaymentMethodId($primaryMethod->getId());

        $page = $this->getPageLoader()->load($request, $context);
        $paymentMethods = \array_values($page->getPaymentMethods()->getElements());

        static::assertSame($primaryMethod->getId(), $paymentMethods[0]->getId());
    }

    public function testShouldNotAllowPaymentMethodChangeOnCertainTransactionStates(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();
        $orderId = $this->placeRandomOrder($context);
        $request->attributes->set('orderId', $orderId);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertTrue($page->isPaymentChangeable());

        $this->setOrderToTransactionState(
            $orderId,
            $context,
            StateMachineTransitionActions::ACTION_AUTHORIZE
        );

        $page = $this->getPageLoader()->load($request, $context);

        static::assertFalse($page->isPaymentChangeable());
    }

    protected function getPageLoader(): AccountEditOrderPageLoader
    {
        return static::getContainer()->get(AccountEditOrderPageLoader::class);
    }

    private function setOrderToTransactionState(
        string $orderId,
        SalesChannelContext $context,
        string $transactionState
    ): void {
        $order = $this->getOrder($orderId, $context);

        $stateMachineRegistry = static::getContainer()->get(StateMachineRegistry::class);

        static::assertInstanceOf(OrderTransactionCollection::class, $order->getTransactions());

        $orderTransactionEntity = $order->getTransactions()->last();
        static::assertInstanceOf(OrderTransactionEntity::class, $orderTransactionEntity);

        $stateMachineRegistry->transition(
            new Transition(
                OrderTransactionDefinition::ENTITY_NAME,
                $orderTransactionEntity->getId(),
                $transactionState,
                'stateId'
            ),
            $context->getContext()
        );
    }

    private function getOrder(string $orderId, SalesChannelContext $context): OrderEntity
    {
        /** @var EntityRepository<OrderCollection> $orderRepository */
        $orderRepository = static::getContainer()->get('order.repository');
        $criteria = new Criteria([$orderId]);

        $criteria->addAssociations(['stateMachineState', 'transactions.stateMachineState']);

        $order = $orderRepository->search($criteria, $context->getContext())->getEntities()->first();
        static::assertNotNull($order);

        return $order;
    }

    private function createCustomPaymentWithRule(SalesChannelContext $context, string $ruleId): string
    {
        $paymentId = Uuid::randomHex();

        $this->paymentMethodRepository->create([
            [
                'id' => $paymentId,
                'name' => 'Test Payment with Rule',
                'technicalName' => Uuid::randomHex(),
                'description' => 'Payment rule test',
                'active' => true,
                'afterOrderEnabled' => true,
                'availabilityRuleId' => $ruleId,
                'salesChannels' => [
                    [
                        'id' => $context->getSalesChannelId(),
                    ],
                ],
            ],
        ], $context->getContext());

        return $paymentId;
    }

    /**
     * @param array<string, int> $options
     */
    private function createCustomPaymentMethod(SalesChannelContext $context, array $options): PaymentMethodEntity
    {
        $paymentId = Uuid::randomHex();

        $data = \array_replace_recursive(
            [
                'id' => $paymentId,
                'name' => 'Test Payment',
                'technicalName' => Uuid::randomHex(),
                'description' => 'Payment test',
                'active' => true,
                'afterOrderEnabled' => true,
                'salesChannels' => [
                    [
                        'id' => $context->getSalesChannelId(),
                    ],
                ],
            ],
            $options
        );

        $this->paymentMethodRepository->create([$data], $context->getContext());

        $paymentMethod = $this->paymentMethodRepository->search(new Criteria([$paymentId]), $context->getContext())->getEntities()->first();
        static::assertNotNull($paymentMethod);

        return $paymentMethod;
    }
}

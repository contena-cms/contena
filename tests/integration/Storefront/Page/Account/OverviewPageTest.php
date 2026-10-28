<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Storefront\Page\Account;

use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Page\Account\Overview\AccountOverviewPageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class OverviewPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;
    private const LAST_TRANSACTION_ID = '00000000000000000000000000000000';

    public function testItLoadsTheOverview(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();
        $orderId = $this->placeRandomOrder($context);
        static::getContainer()->get('order_transaction.repository')->create([
            [
                // this id would result in being the first transaction with wrong sorting
                'id' => self::LAST_TRANSACTION_ID,
                'orderId' => $orderId,
                'amount' => new CalculatedPrice(10.0, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'paymentMethodId' => $this->getValidPaymentMethodId(),
                'stateId' => $this->getStateMachineState(OrderTransactionStates::STATE_MACHINE, OrderTransactionStates::STATE_OPEN),
            ],
        ], $context->getContext());

        static::getContainer()->get('order.repository')->update([
            [
                'id' => $orderId,
                'primaryOrderTransactionId' => self::LAST_TRANSACTION_ID,
            ],
        ], $context->getContext());

        $event = null;
        $this->catchEvent(AccountOverviewPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context, $this->createCustomer());

        $order = $page->getNewestOrder();
        static::assertInstanceOf(OrderEntity::class, $order);

        if (Feature::isActive('v6.8.0.0')) {
            $transaction = $order->getPrimaryOrderTransaction();
            static::assertNotNull($transaction);
            static::assertSame(self::LAST_TRANSACTION_ID, $transaction->getId());
        } else {
            $transactions = $order->getTransactions();
            static::assertNotNull($transactions);
            static::assertCount(2, $transactions);
            $transaction = $transactions->last();
            static::assertNotNull($transaction);
            static::assertSame(self::LAST_TRANSACTION_ID, $transaction->getId());
        }
        self::assertPageEvent(AccountOverviewPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testSalesChannelRestriction(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithLoggedInCustomerAndWithNavigation();
        $testContext = $this->createSalesChannelContext();

        $order = $this->placeRandomOrder($context);
        static::getContainer()->get('order.repository')->update([
            [
                'id' => $order,
                'salesChannelId' => $testContext->getSalesChannelId(),
            ],
        ], $context->getContext());

        $event = null;
        $this->catchEvent(AccountOverviewPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context, $this->createCustomer());

        static::assertNull($page->getNewestOrder());
        self::assertPageEvent(AccountOverviewPageLoadedEvent::class, $event, $context, $request, $page);
    }

}

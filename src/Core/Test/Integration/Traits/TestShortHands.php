<?php declare(strict_types=1);

namespace Contena\Core\Test\Integration\Traits;

use Doctrine\DBAL\Connection;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\SalesChannel\CartService;
use Contena\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Contena\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextService;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Integration\Helper\MailEventListener;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
trait TestShortHands
{
    use KernelTestBehaviour;

    /**
     * @param array<string, mixed> $options
     */
    protected function getContext(?string $token = null, array $options = [], string $salesChannelId = TestDefaults::SALES_CHANNEL): SalesChannelContext
    {
        $token ??= Uuid::randomHex();

        return static::getContainer()->get(SalesChannelContextFactory::class)
            ->create($token, $salesChannelId, $options);
    }

    protected function addProductToCart(string $id, SalesChannelContext $context): Cart
    {
        $product = static::getContainer()->get(ProductLineItemFactory::class)
            ->create(['id' => $id, 'referencedId' => $id], $context);

        $cart = static::getContainer()->get(CartService::class)
            ->getCart($context->getToken(), $context);

        return static::getContainer()->get(CartService::class)
            ->add($cart, $product, $context);
    }

    protected function order(Cart $cart, SalesChannelContext $context, ?RequestDataBag $data = null): string
    {
        return static::getContainer()->get(CartService::class)
            ->order($cart, $context, $data ?? new RequestDataBag());
    }

    protected function assertProductInOrder(string $orderId, string $productId): OrderLineItemEntity
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        $criteria->addFilter(new AndFilter([
            new EqualsFilter('referencedId', $productId),
            new EqualsFilter('type', LineItem::PRODUCT_LINE_ITEM_TYPE),
            new EqualsFilter('orderId', $orderId),
        ]));

        $exists = static::getContainer()->get('order_line_item.repository')
            ->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertCount(1, $exists);

        $item = $exists->first();

        static::assertInstanceOf(OrderLineItemEntity::class, $item);

        return $item;
    }

    protected function assertLineItemTotalPrice(Cart $cart, string $id, float $price): void
    {
        $item = $cart->get($id);

        static::assertInstanceOf(LineItem::class, $item, \sprintf('Can not find line item with id %s', $id));

        static::assertInstanceOf(CalculatedPrice::class, $item->getPrice(), \sprintf('Line item with id %s has no price', $id));

        static::assertSame($price, $item->getPrice()->getTotalPrice(), \sprintf('Line item with id %s has wrong total price', $id));
    }

    protected function assertLineItemUnitPrice(Cart $cart, string $id, float $price): void
    {
        $item = $cart->get($id);

        static::assertInstanceOf(LineItem::class, $item, \sprintf('Can not find line item with id %s', $id));

        static::assertInstanceOf(CalculatedPrice::class, $item->getPrice(), \sprintf('Line item with id %s has no price', $id));

        static::assertSame($price, $item->getPrice()->getUnitPrice(), \sprintf('Line item with id %s has wrong unit price', $id));
    }

    protected function assertLineItemInCart(Cart $cart, string $id): void
    {
        $item = $cart->get($id);

        static::assertInstanceOf(LineItem::class, $item, \sprintf('Can not find line item with id %s', $id));
    }

    protected function login(SalesChannelContext $context, ?string $customerId = null): SalesChannelContext
    {
        if ($customerId === null) {
            $customer = new CustomerBuilder(
                new IdsCollection(),
                Uuid::randomHex(),
                $context->getSalesChannelId()
            );

            static::getContainer()->get('customer.repository')->create(
                [$customer->build()],
                Context::createDefaultContext()
            );

            $customerId = $customer->id;
        }

        return $this->getContext($context->getToken(), [
            SalesChannelContextService::CUSTOMER_ID => $customerId,
        ], $context->getSalesChannelId());
    }

    protected function assertMailSent(MailEventListener $listener, string $type): void
    {
        static::assertTrue($listener->sent($type), \sprintf('Mail with type %s was not sent', $type));
    }

    protected function mailListener(\Closure $closure): mixed
    {
        $mapping = static::getContainer()->get(Connection::class)
            ->fetchAllKeyValue('SELECT LOWER(HEX(id)), technical_name FROM mail_template_type');

        $listener = new MailEventListener($mapping);

        EventHookDispatcher::fromContainer(static::getContainer())->on(FlowSendMailActionEvent::class, $listener);

        $result = $closure($listener);

        EventHookDispatcher::fromContainer(static::getContainer())->off(FlowSendMailActionEvent::class, $listener);

        return $result;
    }

    private function assertStock(string $productId, int $stock, int $available): void
    {
        /** @var array{stock: string, available_stock: string}|false $stocks */
        $stocks = static::getContainer()->get(Connection::class)->fetchAssociative(
            'SELECT stock, available_stock FROM product WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($productId)]
        );

        static::assertNotFalse($stocks, \sprintf('Product with id %s not found', $productId));

        static::assertSame($stock, (int) $stocks['stock'], \sprintf('Product with id %s has wrong stock', $productId));

        static::assertSame($available, (int) $stocks['available_stock'], \sprintf('Product with id %s has wrong available stock', $productId));
    }
}

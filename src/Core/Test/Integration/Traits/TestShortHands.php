<?php declare(strict_types=1);

namespace Contena\Core\Test\Integration\Traits;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
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

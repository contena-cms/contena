<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Shared\MailFlow\DataProvider;

use PHPUnit\Framework\Attributes\CoversClass;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Contena\Core\Content\Shared\MailFlow\DataProvider\OrderTransactionProvider;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @extends AbstractProviderTestCase<OrderTransactionProvider>
 */
#[CoversClass(OrderTransactionProvider::class)]
class OrderTransactionProviderTest extends AbstractProviderTestCase
{
    protected function createProvider(
        EventDispatcherInterface $eventDispatcher,
        ContainerInterface $container,
    ): OrderTransactionProvider {
        return new OrderTransactionProvider($eventDispatcher, $container);
    }

    protected function getEntityName(): string
    {
        return OrderTransactionDefinition::ENTITY_NAME;
    }
}

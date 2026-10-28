<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Storer;

use Contena\Core\Content\Flow\Dispatching\Aware\OrderTransactionAware;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Shared\MailFlow\DataProvider\OrderTransactionProvider;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
class OrderTransactionStorer extends FlowStorer
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderTransactionCollection> $orderTransactionRepository
     */
    public function __construct(
        private readonly EntityRepository $orderTransactionRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly OrderTransactionProvider $orderTransactionProvider,
    ) {
    }

    /**
     * @param array<string, mixed> $stored
     *
     * @return array<string, mixed>
     */
    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof OrderTransactionAware || isset($stored[OrderTransactionAware::ORDER_TRANSACTION_ID])) {
            return $stored;
        }

        $stored[OrderTransactionAware::ORDER_TRANSACTION_ID] = $event->getOrderTransactionId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(OrderTransactionAware::ORDER_TRANSACTION_ID)) {
            return;
        }

        $storable->lazy(
            OrderTransactionAware::ORDER_TRANSACTION,
            $this->lazyLoad(...)
        );
    }

}

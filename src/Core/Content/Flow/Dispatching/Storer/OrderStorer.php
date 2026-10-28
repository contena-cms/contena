<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Storer;

use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Content\Shared\MailFlow\DataProvider\OrderProvider;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\Framework\Event\OrderAware;
use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('after-sales')]
class OrderStorer extends FlowStorer
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly OrderProvider $orderProvider,
    ) {
    }

    public function store(FlowEventAware $event, array $stored): array
    {
        if (!$event instanceof OrderAware || isset($stored[OrderAware::ORDER_ID])) {
            return $stored;
        }

        $stored[OrderAware::ORDER_ID] = $event->getOrderId();

        return $stored;
    }

    public function restore(StorableFlow $storable): void
    {
        if (!$storable->hasStore(OrderAware::ORDER_ID)) {
            return;
        }

        $storable->setData(OrderAware::ORDER_ID, $storable->getStore(OrderAware::ORDER_ID));

        $storable->lazy(
            OrderAware::ORDER,
            $this->lazyLoad(...)
        );
    }

}

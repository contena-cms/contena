<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Action;

use Contena\Core\Content\Flow\Dispatching\DelayableAction;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Event\OrderAware;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class GrantDownloadAccessAction extends FlowAction implements DelayableAction
{
    /**
     * @param EntityRepository<OrderLineItemDownloadCollection> $orderLineItemDownloadRepository
     */
    public function __construct(private readonly EntityRepository $orderLineItemDownloadRepository)
    {
    }

    public static function getName(): string
    {
        return 'action.grant.download.access';
    }

    /**
     * @return list<string>
     */
    public function requirements(): array
    {
        return [OrderAware::class];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        if (!$flow->hasData(OrderAware::ORDER)) {
            return;
        }

        /** @var OrderEntity $order */
        $order = $flow->getData(OrderAware::ORDER);

        $this->update($flow->getContext(), $flow->getConfig(), $order);
    }

}

<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Flow\Dispatching\DelayableAction;
use Contena\Core\Content\Flow\Dispatching\StorableFlow;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\OrderAware;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
class SetOrderCustomFieldAction extends FlowAction implements DelayableAction
{
    use CustomFieldActionTrait;

    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityRepository $orderRepository
    ) {
    }

    public static function getName(): string
    {
        return 'action.set.order.custom.field';
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
        if (!$flow->hasData(OrderAware::ORDER_ID)) {
            return;
        }

        $this->update($flow->getContext(), $flow->getConfig(), $flow->getData(OrderAware::ORDER_ID));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function update(Context $context, array $config, string $orderId): void
    {
        $order = $this->orderRepository->search(new Criteria([$orderId]), $context)->getEntities()->first();

        $customFields = $this->getCustomFieldForUpdating($order?->getCustomFields(), $config);
        if ($customFields === null) {
            return;
        }

        $customFields = $customFields === [] ? null : $customFields;

        $this->orderRepository->update([
            [
                'id' => $orderId,
                'customFields' => $customFields,
            ],
        ], $context);
    }
}

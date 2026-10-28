<?php declare(strict_types=1);

namespace Contena\Core\Content\Shared\MailFlow\DataProvider;

use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Contena\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @extends AbstractProvider<OrderTransactionEntity, OrderTransactionCollection>
 */
#[Package('after-sales')]
class OrderTransactionProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return OrderTransactionDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        return new Criteria([$entityId]);
    }
}

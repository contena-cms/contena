<?php declare(strict_types=1);

namespace Contena\Core\Content\Shared\MailFlow\DataProvider;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

/**
 * @internal
 *
 * @extends AbstractProvider<OrderEntity, OrderCollection>
 */
class OrderProvider extends AbstractProvider
{
    public function getEntityName(): string
    {
        return OrderDefinition::ENTITY_NAME;
    }

    protected function constructCriteria(string $entityId): Criteria
    {
        $criteria = new Criteria([$entityId]);

        $criteria->addAssociations([
            'primaryOrderDelivery',
            'primaryOrderTransaction',
            'orderUser',
            'orderUser.salutation',
            'lineItems.downloads.media',
            'lineItems.cover',
            'deliveries.shippingMethod',
            'deliveries.shippingOrderAddress.country',
            'deliveries.shippingOrderAddress.countryState',
            'stateMachineState',
            'transactions.stateMachineState',
            'transactions.paymentMethod',
            'deliveries.stateMachineState',
            'currency',
            'addresses.country',
            'addresses.countryState',
            'tags',
            'documents',
        ]);

        $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));

        return $criteria;
    }
}

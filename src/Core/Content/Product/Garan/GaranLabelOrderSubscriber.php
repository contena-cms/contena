<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Garan;

use Contena\Core\Checkout\Cart\Order\CartConvertedEvent;
use Contena\Core\Content\Product\ProductCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Stores the GARAN label values of their product in the payload of order line items that did not get them from the
 * cart ({@see ProductCartProcessor}), so every order shows the label it was placed with.
 *
 * @internal
 */
#[Package('inventory')]
class GaranLabelOrderSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly GaranLabelResolver $resolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CartConvertedEvent::class => 'storeLabels',
        ];
    }

}

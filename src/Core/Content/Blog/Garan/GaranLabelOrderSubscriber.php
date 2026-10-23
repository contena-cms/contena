<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Garan;

use Contena\Core\Checkout\Cart\Order\CartConvertedEvent;
use Contena\Core\Checkout\CheckoutPermissions;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Stores the GARAN label values in the payload of the order's blog line items, so the order keeps showing the
 * label it was placed with. Every order conversion passes here, whichever cart processors calculated the cart.
 *
 * @internal
 */
class GaranLabelOrderSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<BlogCollection> $blogRepository
     */
    public function __construct(
        private readonly EntityRepository $blogRepository,
        private readonly GaranLabelResolver $resolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CartConvertedEvent::class => 'storeLabels',
        ];
    }

    public function storeLabels(CartConvertedEvent $event): void
    {
        $order = $event->getConvertedCart();

        // an edited order keeps the GARAN label it was placed with
        $keepStoredLabels = $event->getCart()->getBehavior()?->hasPermission(CheckoutPermissions::SKIP_BLOG_RECALCULATION) ?? false;

        $blogIds = [];

        foreach ($order['lineItems'] ?? [] as $key => $lineItem) {
            if (!\is_string($lineItem['blogId'] ?? null)) {
                continue;
            }

            if ($keepStoredLabels && \array_key_exists('garanLabel', $lineItem['payload'] ?? [])) {
                continue;
            }

            $blogIds[$key] = $lineItem['blogId'];
        }

        // empty criteria would load every blog
        if ($blogIds === []) {
            return;
        }

        $criteria = new Criteria(array_values(array_unique($blogIds)));
        $criteria->addAssociation('manufacturer');

        // variants inherit the guarantee and manufacturer
        $blogs = $event->getContext()->enableInheritance(
            fn (Context $context): BlogCollection => $this->blogRepository->search($criteria, $context)->getEntities()
        );

        foreach ($blogIds as $key => $blogId) {
            $blog = $blogs->get($blogId);

            if ($blog === null) {
                continue;
            }

            $order['lineItems'][$key]['payload']['garanLabel'] = $this->resolver->resolveSnapshot($blog);
        }

        $event->setConvertedCart($order);
    }
}

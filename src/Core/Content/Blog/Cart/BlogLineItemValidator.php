<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Cart;

use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartValidatorInterface;
use Contena\Core\Checkout\Cart\Error\ErrorCollection;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\CheckoutPermissions;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

class BlogLineItemValidator implements CartValidatorInterface
{
    public function validate(Cart $cart, ErrorCollection $errors, ChannelContext $context): void
    {
        $behavior = $cart->getBehavior();
        if ($behavior !== null && $behavior->hasPermission(CheckoutPermissions::SKIP_BLOG_STOCK_VALIDATION)) {
            return;
        }

        $blogLineItems = array_filter($cart->getLineItems()->getFlat(), static fn (LineItem $lineItem) => $lineItem->getType() === LineItem::BLOG_LINE_ITEM_TYPE);

        $quantities = [];
        $refs = [];
        foreach ($blogLineItems as $lineItem) {
            $blogId = $lineItem->getReferencedId();
            if ($blogId === null) {
                continue;
            }

            $quantities[$blogId] = $lineItem->getQuantity() + ($quantities[$blogId] ?? 0);

            // only needed one time to check max quantity
            $refs[$blogId] = $lineItem;
        }

        foreach ($quantities as $blogId => $quantity) {
            $lineItem = $refs[$blogId];
            $quantityInformation = $lineItem->getQuantityInformation();
            if ($quantityInformation === null) {
                continue;
            }

            $minPurchase = $quantityInformation->getMinPurchase();
            $available = $quantityInformation->getMaxPurchase() ?? 0;
            $steps = $quantityInformation->getPurchaseSteps() ?? 1;

            if ($available >= $quantity) {
                continue;
            }

            $maxAvailable = (int) (floor(($available - $minPurchase) / $steps) * $steps + $minPurchase);

            $cart->addErrors(
                new BlogStockReachedError($blogId, (string) $lineItem->getLabel(), $maxAvailable, false),
            );
        }
    }
}

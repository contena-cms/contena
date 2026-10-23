<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Hook\Pricing;

use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPrice\CalculatedCheapestPrice;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

/**
 * The CheapestPriceFacade is a wrapper around the cheapest price of the blog.
 *
 * @script-service blog
 */
class CheapestPriceFacade extends PriceFacade
{
    /**
     * @internal
     */
    public function __construct(
        protected Entity|LineItem $item,
        protected CalculatedPrice $price,
        protected ScriptPriceStubs $priceStubs,
        protected ChannelContext $context
    ) {
        if (!$this->price instanceof CalculatedCheapestPrice) {
            throw BlogException::invalidCheapestPriceFacade($this->item->getUniqueIdentifier());
        }
        if (!$this->item instanceof Entity) {
            throw BlogException::invalidCheapestPriceFacade($this->item->getUniqueIdentifier());
        }
    }

    /**
     * `reset()` allows to reset the cheapest price to the original price of the blog.
     *
     * @example pricing-cases/blog-pricing.twig 64 1 Reset the blog price to default
     */
    public function reset(): void
    {
        $this->change(null);
    }

    /**
     * `change()` allows to overwrite the cheapest price of the current price scope. The provided price will be recalculated
     * over the quantity price calculator to consider quantity, tax rule and cash rounding configurations.
     *
     * @example pricing-cases/blog-pricing.twig 60 5 Overwrite prices with a static defined collection
     * @example pricing-cases/blog-pricing.twig 92 1 Overwrite the cheapest price with the original price
     * @example pricing-cases/blog-pricing.twig 72 1 Discount the cheapest price by 10%
     *
     * @param PriceFacade|PriceCollection|CalculatedPrice|null $price You can provide different values to overwrite the cheapest price. In case of null, it uses the original single price of the blog.
     * @param bool $range Allows to switch the `hasRange` attribute of the cheapest price
     */
    public function change(PriceFacade|PriceCollection|CalculatedPrice|null $price, bool $range = false): void
    {
        if (!$this->item instanceof Entity) {
            throw BlogException::invalidCheapestPriceFacade($this->item->getUniqueIdentifier());
        }

        if (!$this->price instanceof CalculatedCheapestPrice) {
            throw BlogException::invalidCheapestPriceFacade($this->item->getUniqueIdentifier());
        }

        if ($price === null) {
            /** @var CalculatedPrice $price */
            $price = $this->item->get('calculatedPrice');
        }

        if ($price instanceof PriceFacade) {
            $price = $price->getInner();
        }

        if ($price instanceof PriceCollection) {
            $value = $this->getPriceForTaxState($price, $this->context);

            $definition = new QuantityPriceDefinition(
                $value,
                $this->price->getTaxRules(),
                $this->getQuantity()
            );

            $price = $this->priceStubs->calculateQuantity($definition, $this->context);
        }

        if (!$price instanceof CalculatedPrice) {
            throw BlogException::invalidCheapestPriceFacade($this->item->getUniqueIdentifier());
        }

        $this->price->overwrite(
            $price->getUnitPrice(),
            $price->getTotalPrice(),
            $price->getCalculatedTaxes(),
        );

        $this->price->setHasRange($range);
    }
}

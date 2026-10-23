<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Hook\Pricing;

use Contena\Core\Checkout\Cart\Facade\PriceFacade;
use Contena\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Price\Struct\PriceCollection as CalculatedPriceCollection;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

/**
 * The PriceCollectionFacade is a wrapper around the calculated price collection of a blog. It allows to manipulate the quantity
 * prices by resetting or changing the price collection.
 *
 * @script-service blog
 *
 * @implements \IteratorAggregate<PriceFacade>
 */
class PriceCollectionFacade implements \IteratorAggregate, \Countable
{
    public function __construct(
        private readonly Entity $blog,
        private readonly CalculatedPriceCollection $prices,
        private readonly ScriptPriceStubs $priceStubs,
        private readonly ChannelContext $context
    ) {
    }

    /**
     * The `reset()` functions allows to reset the complete price collection.
     */
    public function reset(): void
    {
        $this->prices->clear();
    }

    /**
     * The `change()` function allows a complete overwrite of the blog quantity prices
     *
     * @param list<array{to: int|'', price: PriceCollection}> $changes
     *
     * @example pricing-cases/blog-pricing.twig 40 5 Overwrite the blog prices with a new quantity price graduation
     */
    public function change(array $changes): void
    {
        $mapped = [];
        foreach ($changes as $change) {
            $mapped[$change['to']] = $change['price'];
        }

        // check for "null" value
        if (!\array_key_exists('', $mapped)) {
            throw BlogException::invalidPriceDefinition();
        }

        $last = $mapped[''];
        unset($mapped['']);

        \ksort($mapped, \SORT_NUMERIC);
        $arrayKeys = \array_keys($mapped);
        \assert($arrayKeys !== []);
        $max = \max($arrayKeys);

        $mapped[$max + 1] = $last;

        $this->prices->clear();

        $rules = $this->context->buildTaxRules($this->blog->get('taxId'));

        foreach ($mapped as $quantity => $price) {
            $value = $this->getPriceForTaxState($price, $this->context);

            $definition = new QuantityPriceDefinition($value, $rules, $quantity);

            $this->prices->add(
                $this->priceStubs->calculateQuantity($definition, $this->context)
            );
        }
    }

    /**
     * @internal
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator(
            $this->prices->map(function (CalculatedPrice $price) {
                return new PriceFacade($this->blog, $price, $this->priceStubs, $this->context);
            })
        );
    }

    /**
     * The `count()` function returns the number of prices which are stored inside this collection.
     *
     * @return int Returns the number of prices which are stored inside this collection
     */
    public function count(): int
    {
        return $this->prices->count();
    }

    private function getPriceForTaxState(PriceCollection $price, ChannelContext $context): float
    {
        $currency = $price->getCurrencyPrice($this->context->getCurrencyId());

        if (!$currency instanceof Price) {
            throw BlogException::invalidPriceDefinition();
        }

        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            return $currency->getGross();
        }

        return $currency->getNet();
    }
}

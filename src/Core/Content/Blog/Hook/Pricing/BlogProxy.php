<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Hook\Pricing;

use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPrice\CalculatedCheapestPrice;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

/**
 * The `BlogProxy` is a wrapper for the `ChannelBlogEntity`. It provides access to all properties of the blog,
 * but also wraps some data into helper facade classes like `PriceFacade` or `PriceCollectionFacade`.
 *
 * @script-service blog
 *
 * @implements \ArrayAccess<string, mixed>
 */
class BlogProxy implements \ArrayAccess
{
    public function __construct(
        private readonly Entity $blog,
        private readonly ChannelContext $context,
        private readonly ScriptPriceStubs $priceStubs
    ) {
    }

    /**
     * The `__get()` function allows access to all properties of the [ChannelBlogEntity](https://github.com/contena-cms/contena/blob/trunk/src/Core/Content/Blog/Channel/ChannelBlogEntity.php)
     *
     * @param string $name Name of the property to access
     *
     * @return mixed Returns the value of the property. The value is `mixed` due to the fact that all properties are accessed via `__get()`
     *
     * @example pricing-cases/blog-pricing.twig 42 3 Access the blog properties
     */
    public function __get(string $name): mixed
    {
        return $this->offsetGet($name);
    }

    /**
     * @internal
     */
    public function __set(string $name, mixed $value): void
    {
        $this->offsetSet($name, $value);
    }

    /**
     * @internal
     */
    public function offsetExists(mixed $property): bool
    {
        return \property_exists($this->blog, $property);
    }

    /**
     * @internal
     */
    public function offsetGet(mixed $property): mixed
    {
        return match ($property) {
            'calculatedCheapestPrice' => $this->calculatedCheapestPrice(),
            'calculatedPrice' => $this->calculatedPrice(),
            'calculatedPrices' => $this->calculatedPrices(),
            // @phpstan-ignore property.dynamicName (Allow the dynamic access for sales channel blog fields)
            default => $this->blog->$property,
        };
    }

    /**
     * The `calculatedCheapestPrice` property returns the cheapest price of the blog. The price object will
     * be wrapped into a `PriceFacade` object which allows to manipulate the price.
     *
     * @return PriceFacade|null Returns a `PriceFacade` if the blog has a calculated cheapest price, otherwise `null`
     */
    public function calculatedCheapestPrice(): ?PriceFacade
    {
        return $this->blog->get('calculatedCheapestPrice') ? new CheapestPriceFacade(
            $this->blog,
            $this->blog->get('calculatedCheapestPrice'),
            $this->priceStubs,
            $this->context
        ) : null;
    }

    /**
     * The `calculatedPrice` property returns the price of the blog. The price object will
     * be wrapped into a `PriceFacade` object which allows to manipulate the price.
     *
     * @return PriceFacade|null Returns a `PriceFacade` if the blog has a price, otherwise `null`
     */
    public function calculatedPrice(): ?PriceFacade
    {
        return $this->blog->get('calculatedPrice') ? new PriceFacade(
            $this->blog,
            $this->blog->get('calculatedPrice'),
            $this->priceStubs,
            $this->context
        ) : null;
    }

    /**
     * The `calculatedPrices` property returns the price of the blog. The price object will
     * be wrapped into a `PriceCollectionFacade` object which allows to manipulate the collection.
     *
     * @return PriceCollectionFacade|null Returns a `PriceCollectionFacade` if the blog has graduated prices, otherwise `null`
     */
    public function calculatedPrices(): ?PriceCollectionFacade
    {
        return $this->blog->get('calculatedPrices') ? new PriceCollectionFacade(
            $this->blog,
            $this->blog->get('calculatedPrices'),
            $this->priceStubs,
            $this->context
        ) : null;
    }

    /**
     * @internal
     */
    public function offsetSet(mixed $property, mixed $value): void
    {
        throw BlogException::proxyManipulationNotAllowed($property);
    }

    /**
     * @internal
     */
    public function offsetUnset(mixed $property): void
    {
        throw BlogException::proxyManipulationNotAllowed($property);
    }
}

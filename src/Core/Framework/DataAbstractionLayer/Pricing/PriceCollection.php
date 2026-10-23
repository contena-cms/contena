<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Pricing;

use Contena\Core\Defaults;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Collection;

/**
 * @extends Collection<Price>
 */
class PriceCollection extends Collection
{
    public function add($element): void
    {
        $this->set($element->getCurrencyId(), $element);
    }

    public function set($key, $element): void
    {
        parent::set($element->getCurrencyId(), $element);
    }

    public function getCurrencyPrice(string $currencyId, bool $fallback = true): ?Price
    {
        $price = $this->get($currencyId);

        if ($price) {
            return $price;
        }

        if ($currencyId === Defaults::CURRENCY) {
            return null;
        }

        if (!$fallback) {
            return null;
        }

        return $this->get(Defaults::CURRENCY);
    }

    public function getApiAlias(): string
    {
        return 'price_collection';
    }

    protected function getExpectedClass(): ?string
    {
        return Price::class;
    }
}

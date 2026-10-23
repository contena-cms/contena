<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Processor\_fixtures;

use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Price\Struct\CurrencyPriceDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @phpstan-ignore class.extendsFinalByPhpDoc
 */
class AbsoluteItem extends LineItem
{
    public function __construct(
        float $price,
        ?string $id = null
    ) {
        parent::__construct($id ?? Uuid::randomHex(), LineItem::DISCOUNT_LINE_ITEM);

        $this->priceDefinition = new CurrencyPriceDefinition(new PriceCollection([
            new Price(Defaults::CURRENCY, $price, $price, false),
        ]));
        $this->removable = true;
    }
}

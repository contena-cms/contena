<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Processor\_fixtures;

use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @phpstan-ignore class.extendsFinalByPhpDoc
 */
class QuantityItem extends LineItem
{
    public function __construct(
        float $price,
        TaxRuleCollection $taxes,
        bool $good = true,
        int $quantity = 1
    ) {
        parent::__construct(Uuid::randomHex(), LineItem::BLOG_LINE_ITEM_TYPE, null, $quantity);

        $this->priceDefinition = new QuantityPriceDefinition($price, $taxes, $quantity);
        $this->setGood($good);
    }
}

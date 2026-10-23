<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogPrice;

use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<BlogPriceEntity>
 */
class BlogPriceCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_price_collection';
    }

    public function filterByRuleId(string $ruleId): self
    {
        return $this->filter(static fn (BlogPriceEntity $price) => $ruleId === $price->getRuleId());
    }

    public function sortByQuantity(): void
    {
        $this->sort(static fn (BlogPriceEntity $a, BlogPriceEntity $b) => $a->getQuantityStart() <=> $b->getQuantityStart());
    }

    public function sortByPrice(Context $context): void
    {
        $this->sort(static function (BlogPriceEntity $a, BlogPriceEntity $b) use ($context) {
            $a = $a->getPrice()->first();
            $b = $b->getPrice()->first();

            if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
                return ($a ? $a->getGross() : 0) <=> ($b ? $b->getGross() : 0);
            }

            return ($a ? $a->getNet() : 0) <=> ($b ? $b->getNet() : 0);
        });
    }

    protected function getExpectedClass(): string
    {
        return BlogPriceEntity::class;
    }
}

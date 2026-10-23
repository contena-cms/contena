<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Price;

use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPrice\CalculatedCheapestPrice;
use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPrice\CheapestPrice;
use Contena\Core\Content\Blog\Extension\BlogPriceCalculationExtension;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Unit\UnitCollection;

class BlogPriceCalculator extends AbstractBlogPriceCalculator
{
    private ?UnitCollection $units = null;


    public function getDecorated(): AbstractBlogPriceCalculator
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @param iterable<Entity> $blogs
     */
    public function calculate(iterable $blogs, ChannelContext $context): void
    {
        // allows full service decoration
        $this->extensions->publish(
            name: BlogPriceCalculationExtension::NAME,
            extension: new BlogPriceCalculationExtension($blogs, $context),
            function: $this->_calculate(...)
        );
    }

    public function reset(): void
    {
        $this->units = null;
    }

    /**
     * @param iterable<Entity> $blogs
     */
    private function _calculate(iterable $blogs, ChannelContext $context): void
    {
        $units = $this->getUnits($context);

        foreach ($blogs as $blog) {
            $this->calculatePrice($blog, $context, $units);
            $this->calculateAdvancePrices($blog, $context, $units);
            $this->calculateCheapestPrice($blog, $context, $units);
        }
    }

    private function calculatePrice(Entity $blog, ChannelContext $context, UnitCollection $units): void
    {
        $price = $blog->get('price');
        $taxId = $blog->get('taxId');

        if ($price === null || $taxId === null) {
            return;
        }
        $reference = ReferencePriceDto::createFromEntity($blog);

        $definition = $this->buildDefinition($blog, $price, $context, $units, $reference);

        $price = $this->calculator->calculate($definition, $context);

        $blog->assign([
            'calculatedPrice' => $price,
        ]);
    }

    private function calculateAdvancePrices(Entity $blog, ChannelContext $context, UnitCollection $units): void
    {
        $prices = $blog->get('prices');

        $blog->assign(['calculatedPrices' => new CalculatedPriceCollection()]);
        if ($prices === null) {
            return;
        }

        if (!$prices instanceof EntityCollection || $prices->count() === 0) {
            return;
        }

        $prices = $this->filterRulePrices($prices, $context);
        if ($prices === null) {
            return;
        }
        $prices->sort(fn (Entity $a, Entity $b) => $a->get('quantityStart') <=> $b->get('quantityStart'));

        $reference = ReferencePriceDto::createFromEntity($blog);

        $calculated = new CalculatedPriceCollection();
        foreach ($prices as $price) {
            $quantityStart = $price->get('quantityStart');
            $quantityEnd = $price->get('quantityEnd');
            $priceObj = $price->get('price');

            if (!$priceObj instanceof PriceCollection || (!\is_int($quantityStart) && !\is_int($quantityEnd))) {
                continue;
            }

            $quantity = \is_int($quantityEnd) ? $quantityEnd : $quantityStart;

            $definition = $this->buildDefinition($blog, $priceObj, $context, $units, $reference, $quantity);

            $calculated->add($this->calculator->calculate($definition, $context));
        }

        $blog->assign(['calculatedPrices' => $calculated]);
    }

    private function calculateCheapestPrice(Entity $blog, ChannelContext $context, UnitCollection $units): void
    {
        $cheapest = $blog->get('cheapestPrice');

        if ($blog->get('taxId') === null) {
            return;
        }

        if (!$cheapest instanceof CheapestPrice) {
            $price = $blog->get('price');
            if ($price === null) {
                return;
            }

            $reference = ReferencePriceDto::createFromEntity($blog);

            $definition = $this->buildDefinition($blog, $price, $context, $units, $reference);

            $calculated = CalculatedCheapestPrice::createFrom(
                $this->calculator->calculate($definition, $context)
            );

            $prices = $blog->get('calculatedPrices');

            $hasRange = $prices instanceof CalculatedPriceCollection && $prices->count() > 1;

            $calculated->setHasRange($hasRange);

            $blog->assign(['calculatedCheapestPrice' => $calculated]);

            return;
        }

        $reference = ReferencePriceDto::createFromCheapestPrice($cheapest);

        $definition = $this->buildDefinition($blog, $cheapest->getPrice(), $context, $units, $reference);

        $calculated = CalculatedCheapestPrice::createFrom(
            $this->calculator->calculate($definition, $context)
        );
        $calculated->setVariantId($cheapest->getVariantId());

        $calculated->setHasRange($cheapest->hasRange());

        $blog->assign(['calculatedCheapestPrice' => $calculated]);
    }


    private function getPriceValue(PriceCollection $price, ChannelContext $context): float
    {
        $currency = $price->getCurrencyPrice($context->getCurrencyId());
        if ($currency === null) {
            throw BlogException::noPriceForCurrency($context->getCurrency());
        }

        $value = $this->getPriceForTaxState($currency, $context);

        if ($currency->getCurrencyId() !== $context->getCurrencyId()) {
            $value *= $context->getContext()->getCurrencyFactor();
        }

        return $value;
    }

    private function getPriceForTaxState(Price $price, ChannelContext $context): float
    {
        if ($context->getTaxState() === CartPrice::TAX_STATE_GROSS) {
            return $price->getGross();
        }

        return $price->getNet();
    }

    private function getListPrice(PriceCollection $prices, ChannelContext $context): ?float
    {
        $price = $prices->getCurrencyPrice($context->getCurrencyId());
        if ($price === null || $price->getListPrice() === null) {
            return null;
        }

        $value = $this->getPriceForTaxState($price->getListPrice(), $context);

        if ($price->getCurrencyId() !== $context->getCurrencyId()) {
            $value *= $context->getContext()->getCurrencyFactor();
        }

        return $value;
    }

    private function getRegulationPrice(PriceCollection $prices, ChannelContext $context): ?float
    {
        $price = $prices->getCurrencyPrice($context->getCurrencyId());
        if ($price === null || $price->getRegulationPrice() === null) {
            return null;
        }

        $taxPrice = $this->getPriceForTaxState($price, $context);
        $value = $this->getPriceForTaxState($price->getRegulationPrice(), $context);

        if ($taxPrice === 0.0) {
            return null;
        }

        if ($price->getCurrencyId() !== $context->getCurrencyId()) {
            $value *= $context->getContext()->getCurrencyFactor();
        }

        return $value;
    }


    /**
     * @param EntityCollection<BlogPriceEntity|PartialEntity> $rules
     *
     * @return EntityCollection<BlogPriceEntity|PartialEntity>|null
     */
    private function filterRulePrices(EntityCollection $rules, ChannelContext $context): ?EntityCollection
    {
        if ($context->getRuleIds() === []) {
            return null;
        }

        $availableRuleIds = [];
        foreach ($rules as $price) {
            $priceRuleId = $price->get('ruleId');
            if (\is_string($priceRuleId)) {
                $availableRuleIds[$priceRuleId] = true;
            }
        }

        foreach ($context->getRuleIds() as $ruleId) {
            if (!\array_key_exists($ruleId, $availableRuleIds)) {
                continue;
            }

            return $rules->filter(fn (Entity $price) => $ruleId === $price->get('ruleId'));
        }

        return null;
    }

    private function getUnits(ChannelContext $context): UnitCollection
    {
        if ($this->units !== null) {
            return $this->units;
        }

        $criteria = new Criteria();
        $criteria->setTitle('blog-price-calculator::units');

        $units = $this->unitRepository
            ->search($criteria, $context->getContext())
            ->getEntities();

        return $this->units = $units;
    }
}

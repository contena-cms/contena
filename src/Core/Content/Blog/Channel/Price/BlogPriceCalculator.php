<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Price;

use Contena\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Price\Struct\PriceCollection as CalculatedPriceCollection;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Price\Struct\ReferencePriceDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogPrice\BlogPriceEntity;
use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPrice\CalculatedCheapestPrice;
use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPrice\CheapestPrice;
use Contena\Core\Content\Blog\Extension\BlogPriceCalculationExtension;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Contena\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Unit\UnitCollection;

class BlogPriceCalculator extends AbstractBlogPriceCalculator
{
    private ?UnitCollection $units = null;

    /**
     * @param EntityRepository<UnitCollection> $unitRepository
     *
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $unitRepository,
        private readonly QuantityPriceCalculator $calculator,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

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

    private function buildDefinition(
        Entity $blog,
        PriceCollection $prices,
        ChannelContext $context,
        UnitCollection $units,
        ReferencePriceDto $reference,
        int $quantity = 1
    ): QuantityPriceDefinition {
        $price = $this->getPriceValue($prices, $context);

        $taxId = $blog->get('taxId');
        $definition = new QuantityPriceDefinition($price, $context->buildTaxRules($taxId), $quantity);
        $definition->setReferencePriceDefinition(
            $this->buildReferencePriceDefinition($reference, $units)
        );
        $definition->setListPrice(
            $this->getListPrice($prices, $context)
        );
        $definition->setRegulationPrice(
            $this->getRegulationPrice($prices, $context)
        );

        return $definition;
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

    private function buildReferencePriceDefinition(ReferencePriceDto $definition, UnitCollection $units): ?ReferencePriceDefinition
    {
        if (
            $definition->getPurchase() === null
            || $definition->getPurchase() <= 0
            || $definition->getUnitId() === null
            || $definition->getReference() === null
            || $definition->getReference() <= 0
            || $definition->getPurchase() === $definition->getReference()
        ) {
            return null;
        }

        $unit = $units->get($definition->getUnitId());
        if ($unit === null) {
            return null;
        }

        return new ReferencePriceDefinition(
            $definition->getPurchase(),
            $definition->getReference(),
            $unit->getTranslation('name')
        );
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

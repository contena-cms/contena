<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\ListPrice;
use Contena\Core\Checkout\Cart\Price\Struct\ReferencePrice;
use Contena\Core\Checkout\Cart\Price\Struct\RegulationPrice;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
class CalculatedPriceFieldSerializer extends JsonFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        $value = json_decode(json_encode($data->getValue(), \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        // a non-array value must survive untouched, `parent::encode()` turns it into a write constraint violation
        if (\is_array($value)) {
            unset($value['extensions']);

            if (\is_array($value['listPrice'] ?? null)) {
                unset($value['listPrice']['extensions']);
            }

            if (\is_array($value['regulationPrice'] ?? null)) {
                unset($value['regulationPrice']['extensions']);
            }
        }

        $data->setValue($value);

        yield from parent::encode($field, $existence, $data, $parameters);
    }

    public function decode(Field $field, mixed $value): ?CalculatedPrice
    {
        if ($value === null) {
            return null;
        }

        $decoded = parent::decode($field, $value);
        if (!\is_array($decoded)) {
            return null;
        }

        $taxRules = array_map(
            static fn (array $tax) => new TaxRule(
                (float) $tax['taxRate'],
                (float) $tax['percentage']
            ),
            $decoded['taxRules']
        );

        $calculatedTaxes = array_map(
            static fn (array $tax) => new CalculatedTax(
                (float) $tax['tax'],
                (float) $tax['taxRate'],
                (float) $tax['price'],
                $tax['label'] ?? null,
            ),
            $decoded['calculatedTaxes']
        );

        $referencePriceDefinition = null;
        if (isset($decoded['referencePrice'])) {
            $refPrice = $decoded['referencePrice'];

            $referencePriceDefinition = new ReferencePrice(
                $refPrice['price'],
                $refPrice['purchaseUnit'],
                $refPrice['referenceUnit'],
                $refPrice['unitName']
            );
        }

        $listPrice = null;
        $listPriceValue = (float) ($decoded['listPrice']['price'] ?? 0);
        if ($listPriceValue > 0) {
            $listPrice = ListPrice::createFromUnitPrice((float) $decoded['unitPrice'], $listPriceValue);
        }

        $regulationPrice = null;
        $regulationPriceValue = (float) ($decoded['regulationPrice']['price'] ?? 0);
        if ($regulationPriceValue > 0) {
            $regulationPrice = RegulationPrice::createFromUnitPrice((float) $decoded['unitPrice'], $regulationPriceValue);
        }

        return new CalculatedPrice(
            (float) $decoded['unitPrice'],
            (float) $decoded['totalPrice'],
            new CalculatedTaxCollection($calculatedTaxes),
            new TaxRuleCollection($taxRules),
            (int) $decoded['quantity'],
            $referencePriceDefinition,
            $listPrice,
            $regulationPrice
        );
    }
}

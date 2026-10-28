<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
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

}

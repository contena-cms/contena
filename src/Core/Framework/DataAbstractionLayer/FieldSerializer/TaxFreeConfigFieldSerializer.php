<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

/**
 * @internal
 */
class TaxFreeConfigFieldSerializer extends JsonFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        // a non-array value must survive untouched, `parent::encode()` turns it into a write constraint violation
        if (\is_array($data->getValue())) {
            $value = $data->getValue();
            unset($value['extensions']);

            $data = new KeyValuePair($data->getKey(), $value, $data->isRaw());
        }

        yield from parent::encode($field, $existence, $data, $parameters);
    }

    public function decode(Field $field, mixed $value): ?TaxFreeConfig
    {
        if ($value === null) {
            return null;
        }

        $raw = json_decode((string) $value, true, 512, \JSON_THROW_ON_ERROR);

        return new TaxFreeConfig(
            (bool) $raw['enabled'],
            (string) $raw['currencyId'],
            (float) $raw['amount']
        );
    }
}

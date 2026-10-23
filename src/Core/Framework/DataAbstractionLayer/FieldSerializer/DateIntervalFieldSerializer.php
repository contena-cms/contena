<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Contena\Core\Framework\DataAbstractionLayer\FieldType\DateInterval;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
class DateIntervalFieldSerializer extends AbstractFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof StorageAware) {
            throw DataAbstractionLayerException::invalidSerializerField(self::class, $field);
        }

        $interval = $data->getValue();

        if ($interval === null) {
            yield $field->getStorageName() => null;

            return;
        }

        if (\is_string($interval)) {
            try {
                $interval = new DateInterval($interval);
            } catch (\Throwable $e) {
                throw DataAbstractionLayerException::invalidDateIntervalFormat($interval, $e);
            }
        }

        $data->setValue($interval);
        $this->validateIfNeeded($field, $existence, $data, $parameters);

        if (!$interval instanceof \DateInterval) {
            yield $field->getStorageName() => null;

            return;
        }

        if (!$interval instanceof DateInterval) {
            yield $field->getStorageName() => (string) DateInterval::createFromDateInterval($interval);

            return;
        }

        yield $field->getStorageName() => (string) $interval;
    }

    /**
     * @param string|null $value
     */
    public function decode(Field $field, $value): ?DateInterval
    {
        if ($value === null) {
            return null;
        }

        try {
            $dateInterval = new DateInterval($value);
        } catch (\Throwable $e) {
            throw DataAbstractionLayerException::invalidDateIntervalFormat($value, $e);
        }

        return $dateInterval;
    }

    protected function getConstraints(Field $field): array
    {
        return [
            new Type(\DateInterval::class),
            new NotNull(),
        ];
    }
}

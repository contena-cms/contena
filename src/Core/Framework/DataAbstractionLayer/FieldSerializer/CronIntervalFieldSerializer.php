<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Cron\CronExpression;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Contena\Core\Framework\DataAbstractionLayer\FieldType\CronInterval;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
class CronIntervalFieldSerializer extends AbstractFieldSerializer
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
            if (!CronExpression::isValidExpression($interval)) {
                throw DataAbstractionLayerException::invalidCronIntervalFormat($interval);
            }

            $interval = new CronExpression($interval);
        }

        $data->setValue($interval);
        $this->validateIfNeeded($field, $existence, $data, $parameters);

        if (!$interval instanceof CronExpression) {
            yield $field->getStorageName() => null;

            return;
        }

        yield $field->getStorageName() => (string) $interval;
    }

    /**
     * @param string|null $value
     */
    public function decode(Field $field, $value): ?CronInterval
    {
        if ($value === null) {
            return null;
        }

        if (!CronInterval::isValidExpression($value)) {
            throw DataAbstractionLayerException::invalidCronIntervalFormat($value);
        }

        return new CronInterval($value);
    }

    protected function getConstraints(Field $field): array
    {
        return [
            new Type(CronExpression::class),
            new NotNull(),
        ];
    }
}

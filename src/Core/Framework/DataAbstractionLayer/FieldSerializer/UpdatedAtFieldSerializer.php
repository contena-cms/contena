<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Psr\Clock\ClockInterface;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
class UpdatedAtFieldSerializer extends DateTimeFieldSerializer
{
    public function __construct(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($validator, $definitionRegistry);
    }

    /**
     * @throws DataAbstractionLayerException
     */
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof UpdatedAtField) {
            throw DataAbstractionLayerException::invalidSerializerField(UpdatedAtField::class, $field);
        }
        if (!$existence->exists()) {
            return;
        }

        $data->setValue($this->clock->now());

        yield from parent::encode($field, $existence, $data, $parameters);
    }
}

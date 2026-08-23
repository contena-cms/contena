<?php
declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Psr\Clock\ClockInterface;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
class CreatedAtFieldSerializer extends DateTimeFieldSerializer
{
    public function __construct(
        ValidatorInterface $validator,
        DefinitionInstanceRegistry $definitionRegistry,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($validator, $definitionRegistry);
    }

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if ($existence->exists()) {
            return;
        }

        if (!$data->getValue()) {
            $data->setValue($this->clock->now());
        }

        yield from parent::encode($field, $existence, $data, $parameters);
    }
}

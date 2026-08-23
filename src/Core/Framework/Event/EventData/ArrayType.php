<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event\EventData;

class ArrayType implements EventDataType
{
    final public const string TYPE = 'array';

    public function __construct(private readonly EventDataType $type)
    {
    }

    public function toArray(): array
    {
        return [
            'type' => self::TYPE,
            'of' => $this->type->toArray(),
        ];
    }
}

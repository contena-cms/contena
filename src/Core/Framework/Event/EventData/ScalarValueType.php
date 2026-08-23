<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event\EventData;

use Contena\Core\Framework\FrameworkException;

class ScalarValueType implements EventDataType
{
    final public const string TYPE_STRING = 'string';
    final public const string TYPE_INT = 'int';
    final public const string TYPE_FLOAT = 'float';
    final public const string TYPE_BOOL = 'bool';

    final public const array VALID_TYPES = [
        self::TYPE_STRING,
        self::TYPE_INT,
        self::TYPE_FLOAT,
        self::TYPE_BOOL,
    ];

    private readonly string $type;

    public function __construct(string $type)
    {
        if (!\in_array($type, self::VALID_TYPES, true)) {
            $message = \sprintf('Invalid type "%s" provided, valid ones are: %s', $type, implode(', ', self::VALID_TYPES));
            throw FrameworkException::invalidArgumentException($message);
        }

        $this->type = $type;
    }

    /**
     * @return array{type: self::TYPE_*}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
        ];
    }
}

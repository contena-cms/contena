<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Attribute;

use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface;

/**
 * Use `#[Serialized]` to configure a custom field serializer. Note: for the default serializer, you should use the proper fields instead.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Serialized extends Field
{
    public const string TYPE = 'serialized';

    /**
     * @param class-string<FieldSerializerInterface> $serializer
     */
    public function __construct(
        public string $serializer,
        public bool|array $api = false,
        public bool $translated = false,
        public ?string $column = null
    ) {
        parent::__construct(type: self::TYPE, translated: $translated, api: $api, column: $column);
    }
}

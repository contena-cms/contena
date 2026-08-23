<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Attribute;

use Contena\Core\Framework\DataAbstractionLayer\Field\Field as DalField;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ListField extends Field
{
    public const string TYPE = 'list';

    /**
     * @param class-string<DalField>|null $fieldType
     */
    public function __construct(
        public ?string $fieldType = null,
        public bool|array $api = false,
        public bool $translated = false,
        public ?string $column = null,
    ) {
        parent::__construct(type: self::TYPE, translated: $translated, api: $api, column: $column);
    }
}

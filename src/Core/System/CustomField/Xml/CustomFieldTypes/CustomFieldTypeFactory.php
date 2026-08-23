<?php declare(strict_types=1);

namespace Contena\Core\System\CustomField\Xml\CustomFieldTypes;

use Contena\Core\System\CustomField\Xml\CustomFieldTypeNotFoundException;

/**
 * @internal
 */
class CustomFieldTypeFactory
{
    private const array TAG_TO_CLASS_MAPPING = [
        'int' => IntField::class,
        'float' => FloatField::class,
        'text' => TextField::class,
        'text-area' => TextAreaField::class,
        'bool' => BoolField::class,
        'datetime' => DateTimeField::class,
        'single-select' => SingleSelectField::class,
        'multi-select' => MultiSelectField::class,
        'single-entity-select' => SingleEntitySelectField::class,
        'multi-entity-select' => MultiEntitySelectField::class,
        'color-picker' => ColorPickerField::class,
        'media-selection' => MediaSelectionField::class,
    ];

    public static function createFromXml(\DOMElement $element): CustomFieldType
    {
        $fieldClass = self::TAG_TO_CLASS_MAPPING[$element->tagName] ?? null;

        if (!$fieldClass) {
            throw new CustomFieldTypeNotFoundException($element->tagName);
        }

        return $fieldClass::fromXml($element);
    }
}

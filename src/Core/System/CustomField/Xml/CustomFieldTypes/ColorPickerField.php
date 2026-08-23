<?php declare(strict_types=1);

namespace Contena\Core\System\CustomField\Xml\CustomFieldTypes;

use Contena\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
class ColorPickerField extends CustomFieldType
{
    protected function toEntityArray(): array
    {
        return [
            'type' => CustomFieldTypes::TEXT,
            'config' => [
                'type' => 'colorpicker',
                'componentName' => 'ct-field',
                'customFieldType' => 'colorpicker',
            ],
        ];
    }
}

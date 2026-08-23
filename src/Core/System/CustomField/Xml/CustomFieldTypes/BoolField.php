<?php declare(strict_types=1);

namespace Contena\Core\System\CustomField\Xml\CustomFieldTypes;

use Contena\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
class BoolField extends CustomFieldType
{
    protected function toEntityArray(): array
    {
        return [
            'type' => CustomFieldTypes::BOOL,
            'config' => [
                'type' => 'checkbox',
                'componentName' => 'ct-field',
                'customFieldType' => 'checkbox',
            ],
        ];
    }
}

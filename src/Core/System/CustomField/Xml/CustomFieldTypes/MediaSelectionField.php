<?php declare(strict_types=1);

namespace Contena\Core\System\CustomField\Xml\CustomFieldTypes;

use Contena\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
class MediaSelectionField extends CustomFieldType
{
    protected function toEntityArray(): array
    {
        return [
            'type' => CustomFieldTypes::TEXT,
            'config' => [
                'componentName' => 'ct-media-field',
                'customFieldType' => 'media',
            ],
        ];
    }
}

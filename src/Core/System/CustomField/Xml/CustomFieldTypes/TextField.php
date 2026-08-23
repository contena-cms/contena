<?php declare(strict_types=1);

namespace Contena\Core\System\CustomField\Xml\CustomFieldTypes;

use Contena\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
class TextField extends CustomFieldType
{
    protected const array TRANSLATABLE_FIELDS = ['label', 'help-text', 'placeholder'];

    /**
     * @var array<string, string>
     */
    protected array $placeholder = [];

    /**
     * @return array<string, string>
     */
    public function getPlaceholder(): array
    {
        return $this->placeholder;
    }

    protected function toEntityArray(): array
    {
        return [
            'type' => CustomFieldTypes::TEXT,
            'config' => [
                'type' => 'text',
                'placeholder' => $this->placeholder,
                'componentName' => 'ct-field',
                'customFieldType' => 'text',
            ],
        ];
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\System\CustomField\Xml\CustomFieldTypes;

use Contena\Core\System\CustomField\CustomFieldTypes;

/**
 * @internal
 */
class FloatField extends CustomFieldType
{
    protected const array TRANSLATABLE_FIELDS = ['label', 'help-text', 'placeholder'];

    /**
     * @var array<string, string>
     */
    protected array $placeholder = [];

    protected ?float $steps = null;

    protected ?float $min = null;

    protected ?float $max = null;

    /**
     * @return array<string, string>
     */
    public function getPlaceholder(): array
    {
        return $this->placeholder;
    }

    public function getSteps(): ?float
    {
        return $this->steps;
    }

    public function getMin(): ?float
    {
        return $this->min;
    }

    public function getMax(): ?float
    {
        return $this->max;
    }

    protected function toEntityArray(): array
    {
        $entityArray = [
            'type' => CustomFieldTypes::FLOAT,
            'config' => [
                'type' => 'number',
                'placeholder' => $this->placeholder,
                'componentName' => 'ct-field',
                'customFieldType' => 'number',
                'numberType' => 'float',
            ],
        ];

        if ($this->max !== null) {
            $entityArray['config']['max'] = $this->max;
        }

        if ($this->min !== null) {
            $entityArray['config']['min'] = $this->min;
        }

        if ($this->steps !== null) {
            $entityArray['config']['step'] = $this->steps;
        }

        return $entityArray;
    }
}

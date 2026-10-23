<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange\DataAbstractionLayer;

use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;

class NumberRangeField extends StringField
{
    public function __construct(
        string $storageName,
        string $propertyName,
        int $maxLength = 64
    ) {
        parent::__construct($storageName, $propertyName, $maxLength);
    }
}

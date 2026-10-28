<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange\DataAbstractionLayer;

use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\Log\Package;

#[Package('framework')]
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

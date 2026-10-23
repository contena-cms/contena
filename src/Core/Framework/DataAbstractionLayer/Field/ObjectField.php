<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;


/**
 * @codeCoverageIgnore
 */
class ObjectField extends JsonField
{
    public function __construct(
        string $storageName,
        string $propertyName
    ) {
        parent::__construct($storageName, $propertyName);
    }
}

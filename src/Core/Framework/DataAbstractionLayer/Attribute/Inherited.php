<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Inherited
{
    /**
     * @param string|null $foreignKey Allows overwriting the expected reference foreign key.
     *                                By default, the DAL expects a foreign key named #table#_id (e.g., product_id) in the reference table.
     *                                Use this parameter when you have multiple inherited references to the same table.
     */
    public function __construct(
        public ?string $foreignKey = null
    ) {
    }
}

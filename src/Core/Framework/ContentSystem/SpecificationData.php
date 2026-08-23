<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem;

use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;

/**
 * Bundles the data requirements derived from the entity definition with the
 * placeholder values derived from the request path and query parameters.
 */
final readonly class SpecificationData
{
    /**
     * @param list<DataRequirement> $dataRequirements
     */
    public function __construct(
        public array $dataRequirements,
        public PlaceholderValues $placeholderValues,
    ) {
    }
}

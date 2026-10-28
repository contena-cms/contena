<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem;

use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

#[Package('framework')]
final readonly class RenderingSpecification
{
    /**
     * @param list<DataRequirement> $dataRequirements
     * @param list<string> $cacheTags
     */
    public function __construct(
        public array $dataRequirements,
        public PlaceholderValues $placeholderValues,
        public Request $request,
        public ?string $targetElementId = null,
        public array $cacheTags = [],
    ) {
    }
}

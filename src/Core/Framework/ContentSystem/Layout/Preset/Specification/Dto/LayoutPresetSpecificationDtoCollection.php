<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Preset\Specification\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final readonly class LayoutPresetSpecificationDtoCollection
{
    /**
     * Keyed by resolved preset id (e.g. "Ct:CategoryPage") so Symfony includes
     * the id in violation property paths: presets[Ct:CategoryPage].layout
     *
     * @param array<string, LayoutPresetSpecificationDto> $presets
     */
    public function __construct(
        #[Assert\Valid]
        public array $presets,
    ) {
    }
}

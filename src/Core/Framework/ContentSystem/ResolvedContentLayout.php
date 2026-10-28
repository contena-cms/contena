<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem;

use Contena\Core\Framework\Log\Package;

#[Package('framework')]
final readonly class ResolvedContentLayout
{
    private function __construct(
        public string $layoutId,
        public RenderingSpecification $specification,
    ) {
    }

    public static function create(string $layoutId, RenderingSpecification $specification): self
    {
        return new self($layoutId, $specification);
    }
}

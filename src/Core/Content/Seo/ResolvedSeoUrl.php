<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo;

final readonly class ResolvedSeoUrl
{
    public function __construct(
        public string $pathInfo,
        public bool $isCanonical,
        public ?string $id = null,
        public ?string $canonicalPathInfo = null,
        public ?string $seoPathInfo = null,
    ) {
    }
}

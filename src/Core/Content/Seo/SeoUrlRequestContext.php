<?php declare(strict_types=1);

namespace Contena\Core\Content\Seo;

final readonly class SeoUrlRequestContext
{
    public function __construct(
        public string $languageId,
        public string $channelId,
        public string $pathInfo,
        public ?string $queryString = null,
    ) {
    }
}

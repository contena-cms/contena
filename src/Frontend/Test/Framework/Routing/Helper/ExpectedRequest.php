<?php declare(strict_types=1);

namespace Contena\Frontend\Test\Framework\Routing\Helper;

/**
 * @internal
 */
class ExpectedRequest
{
    /**
     * @param class-string<\Throwable>|null $exception
     */
    public function __construct(
        public string $url,
        public ?string $baseUrl,
        public ?string $resolvedUrl,
        public ?string $domainId,
        public ?string $channelId,
        public ?bool $isFrontendRequest,
        public ?string $locale,
        public ?string $languageCode,
        public ?string $snippetLanguageCode,
        public ?string $exception = null,
    ) {
    }
}

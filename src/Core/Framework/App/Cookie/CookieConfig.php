<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Cookie;

use Contena\Core\Framework\App\Feature\AppFeatureConfig;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
readonly class CookieConfig implements AppFeatureConfig
{
    /**
     * @param list<array<string, mixed>> $entries
     */
    public function __construct(
        public string $snippetName,
        public ?string $snippetDescription,
        public ?string $cookie,
        public ?string $value,
        public ?int $expiration,
        public array $entries,
    ) {
    }

    public function getName(): string
    {
        return $this->snippetName;
    }
}

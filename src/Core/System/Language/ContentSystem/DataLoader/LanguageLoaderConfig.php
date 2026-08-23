<?php declare(strict_types=1);

namespace Contena\Core\System\Language\ContentSystem\DataLoader;

use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;

/**
 * Configuration for language data loader.
 *
 * @phpstan-type LanguageLoaderConfigData array{
 *   associations?: list<non-empty-string>
 * }
 *
 * @internal
 */
final readonly class LanguageLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param list<non-empty-string> $associations Additional associations to load
     */
    public function __construct(
        public array $associations = [],
    ) {
    }

    /**
     * @return LanguageLoaderConfigData
     */
    public function jsonSerialize(): array
    {
        $data = [];

        if ($this->associations !== []) {
            $data['associations'] = $this->associations;
        }

        return $data;
    }
}

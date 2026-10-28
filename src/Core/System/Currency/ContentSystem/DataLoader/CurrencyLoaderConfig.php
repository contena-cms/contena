<?php declare(strict_types=1);

namespace Contena\Core\System\Currency\ContentSystem\DataLoader;

use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\Log\Package;

/**
 * Configuration for currency data loader.
 *
 * @phpstan-type CurrencyLoaderConfigData array{
 *   associations?: list<non-empty-string>
 * }
 *
 * @internal
 */
#[Package('framework')]
final readonly class CurrencyLoaderConfig extends AbstractContentDataLoaderConfig
{
    /**
     * @param list<non-empty-string> $associations Additional associations to load
     */
    public function __construct(
        public array $associations = [],
    ) {
    }

    /**
     * @return CurrencyLoaderConfigData
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

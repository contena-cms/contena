<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Schema;

use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderProvider;

/**
 * Resolves the source-to-capability map from the registered data loaders.
 *
 * @internal
 *
 * @final
 */
class ContentSystemDataLoaderMapResolver extends AbstractContentSystemDataLoaderMapResolver
{
    private ?ContentSystemDataLoaderMap $map = null;

    public function __construct(
        private readonly DataLoaderProvider $dataLoaderProvider,
    ) {
    }

    public function resolve(): ContentSystemDataLoaderMap
    {
        if ($this->map !== null) {
            return $this->map;
        }

        $sourceToCapabilities = [];
        $sourceToConfigSpecifications = [];
        foreach ($this->dataLoaderProvider->getSources() as $source) {
            $loader = $this->dataLoaderProvider->get($source);
            $sourceToCapabilities[$source] = $loader->producibleTypes();
            $sourceToConfigSpecifications[$source] = $loader->configSpecification();
        }

        return $this->map = new ContentSystemDataLoaderMap($sourceToCapabilities, $sourceToConfigSpecifications);
    }
}

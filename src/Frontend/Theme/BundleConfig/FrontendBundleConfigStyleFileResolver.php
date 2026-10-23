<?php declare(strict_types=1);

namespace Contena\Frontend\Theme\BundleConfig;

use Contena\Core\Framework\Plugin\BundleConfigStyleFileResolver;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
final class FrontendBundleConfigStyleFileResolver implements BundleConfigStyleFileResolver
{
    public function __construct(private readonly FrontendPluginRegistry $registry)
    {
    }

    public function resolveStyleFiles(string $technicalName, string $basePath): array
    {
        $config = $this->registry->getConfigurations()->getByTechnicalName($technicalName);

        if ($config === null) {
            return [];
        }

        return array_values(array_map(
            static fn (string $path) => Path::join($basePath, 'Resources', $path),
            $config->getStyleFiles()->getFilepaths()
        ));
    }
}

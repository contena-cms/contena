<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin;

/**
 * @internal
 */
final class NullBundleConfigStyleFileResolver implements BundleConfigStyleFileResolver
{
    public function resolveStyleFiles(string $technicalName, string $basePath): array
    {
        return [];
    }
}

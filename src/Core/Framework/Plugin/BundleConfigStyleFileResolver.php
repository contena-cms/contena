<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin;

/**
 * @internal
 */
interface BundleConfigStyleFileResolver
{
    /**
     * @return list<string>
     */
    public function resolveStyleFiles(string $technicalName, string $basePath): array;
}

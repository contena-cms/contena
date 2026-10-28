<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface BundleConfigStyleFileResolver
{
    /**
     * @return list<string>
     */
    public function resolveStyleFiles(string $technicalName, string $basePath): array;
}

<?php declare(strict_types=1);

namespace Contena\Core\Framework\Plugin;

use Contena\Core\Framework\Log\Package;

/**
 * @phpstan-type BundleConfig array{
 *         basePath: string,
 *         views: string[],
 *         technicalName: string,
 *         isTheme?: bool,
 *         administration?: array{
 *             path: string,
 *             entryFilePath: string|null,
 *             webpack: string|null,
 *         },
 *         frontend: array{
 *            path: string ,
 *            entryFilePath: string|null,
 *            webpack: string|null,
 *            styleFiles: string[],
 *            hasComponentAssets: bool|null,
 *         }
 *     }
 */
interface BundleConfigGeneratorInterface
{
    /**
     * Returns the bundle config for the webpack plugin injector
     *
     * @return array<string, BundleConfig>
     */
    public function getConfig(): array;
}

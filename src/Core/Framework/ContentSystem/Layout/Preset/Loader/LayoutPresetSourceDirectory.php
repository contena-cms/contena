<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Preset\Loader;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final readonly class LayoutPresetSourceDirectory
{
    public function __construct(
        public string $source,
        public string $path,
        public string $prefix,
    ) {
    }
}

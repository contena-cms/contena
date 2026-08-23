<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Type\Loader;

/**
 * @internal
 */
final readonly class ElementTypeSourceDirectory
{
    public function __construct(
        public string $source,
        public string $path,
        public string $prefix,
    ) {
    }
}

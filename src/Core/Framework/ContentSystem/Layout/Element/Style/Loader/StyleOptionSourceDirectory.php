<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Element\Style\Loader;

/**
 * One filesystem directory a YamlStyleOptionLoader scans, paired with its source label.
 *
 * @internal
 */
final readonly class StyleOptionSourceDirectory
{
    public function __construct(
        public string $source,
        public string $path,
    ) {
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Element\Style\Loader;

use Contena\Core\Framework\Log\Package;

/**
 * One filesystem directory a YamlStyleOptionLoader scans, paired with its source label.
 *
 * @internal
 */
#[Package('framework')]
final readonly class StyleOptionSourceDirectory
{
    public function __construct(
        public string $source,
        public string $path,
    ) {
    }
}

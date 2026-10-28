<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Preset\Loader;

use Contena\Core\Framework\ContentSystem\Layout\Preset\Specification\ContentSystemLayoutPresetSpecification;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
abstract class AbstractContentSystemLayoutPresetLoader
{
    /**
     * @return list<ContentSystemLayoutPresetSpecification>
     */
    abstract public function load(): array;
}

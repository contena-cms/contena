<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Preset\Loader;

use Contena\Core\Framework\ContentSystem\Layout\Preset\Specification\ContentSystemLayoutPresetSpecification;

/**
 * @internal
 */
abstract class AbstractContentSystemLayoutPresetLoader
{
    /**
     * @return list<ContentSystemLayoutPresetSpecification>
     */
    abstract public function load(): array;
}

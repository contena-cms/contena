<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Element\Style\Loader;

use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Specification\StyleOptionSpecification;

/**
 * @internal
 */
abstract class AbstractContentSystemStyleOptionLoader
{
    /**
     * @return list<StyleOptionSpecification>
     */
    abstract public function load(): array;
}

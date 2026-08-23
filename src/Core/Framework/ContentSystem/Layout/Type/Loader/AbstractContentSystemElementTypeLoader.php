<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Type\Loader;

use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;

/**
 * @internal
 */
abstract class AbstractContentSystemElementTypeLoader
{
    /**
     * @return list<ContentSystemElementTypeSpecification>
     */
    abstract public function load(): array;
}

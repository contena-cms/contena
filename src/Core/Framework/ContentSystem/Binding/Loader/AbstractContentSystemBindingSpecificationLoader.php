<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Binding\Loader;

use Contena\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;

/**
 * @internal
 */
abstract class AbstractContentSystemBindingSpecificationLoader
{
    /**
     * @return list<BindingSpecification>
     */
    abstract public function load(): array;
}

<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Binding\Loader;

use Contena\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Contena\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractContentSystemBindingSpecificationLoader
{
    /**
     * @return list<BindingSpecification>
     */
    abstract public function load(): array;
}

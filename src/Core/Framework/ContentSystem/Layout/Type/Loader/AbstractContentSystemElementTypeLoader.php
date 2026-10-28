<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Type\Loader;

use Contena\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Contena\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractContentSystemElementTypeLoader
{
    /**
     * @return list<ContentSystemElementTypeSpecification>
     */
    abstract public function load(): array;
}

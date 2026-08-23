<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing;

use Contena\Frontend\Framework\Routing\Struct\DomainCollection;

abstract class AbstractDomainLoader
{
    abstract public function getDecorated(): AbstractDomainLoader;

    abstract public function loadDomains(): DomainCollection;
}

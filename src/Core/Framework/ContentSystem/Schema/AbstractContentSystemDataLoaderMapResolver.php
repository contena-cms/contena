<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Schema;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
abstract class AbstractContentSystemDataLoaderMapResolver
{
    abstract public function resolve(): ContentSystemDataLoaderMap;
}

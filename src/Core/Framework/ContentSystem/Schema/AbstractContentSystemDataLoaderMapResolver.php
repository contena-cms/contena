<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Schema;

/**
 * @internal
 */
abstract class AbstractContentSystemDataLoaderMapResolver
{
    abstract public function resolve(): ContentSystemDataLoaderMap;
}

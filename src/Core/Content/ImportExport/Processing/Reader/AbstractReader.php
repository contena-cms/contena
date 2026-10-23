<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Processing\Reader;

use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Framework\Log\Package;

abstract class AbstractReader
{
    /**
     * @param resource $resource
     *
     * @return iterable<array<string, mixed>>
     */
    abstract public function read(Config $config, $resource, int $offset): iterable;

    abstract public function getOffset(): int;

    protected function getDecorated(): AbstractReader
    {
        throw new \RuntimeException('Implement getDecorated');
    }
}

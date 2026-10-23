<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Processing\Pipe;

use Contena\Core\Content\ImportExport\Struct\Config;

/**
 * @internal
 */
abstract class AbstractPipe
{
    /**
     * @param iterable<array<string, mixed>> $record
     *
     * @return iterable<array<string, mixed>>
     */
    abstract public function in(Config $config, iterable $record): iterable;

    /**
     * @param iterable<array<string, mixed>> $record
     *
     * @return iterable<array<string, mixed>>
     */
    abstract public function out(Config $config, iterable $record): iterable;
}

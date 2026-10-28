<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Strategy\Import;

use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Content\ImportExport\Struct\ImportResult;
use Contena\Core\Content\ImportExport\Struct\Progress;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
interface ImportStrategyService
{
    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $row
     */
    public function import(
        array $record,
        array $row,
        Config $config,
        Progress $progress,
        Context $context,
    ): ImportResult;

    public function commit(Config $config, Progress $progress, Context $context): ImportResult;
}

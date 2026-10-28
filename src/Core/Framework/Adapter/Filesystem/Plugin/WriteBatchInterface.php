<?php declare(strict_types=1);

namespace Contena\Core\Framework\Adapter\Filesystem\Plugin;

use Contena\Core\Framework\Log\Package;

#[Package('framework')]
interface WriteBatchInterface
{
    public function writeBatch(CopyBatchInput ...$files): void;
}

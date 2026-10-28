<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Event;

use Contena\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Contena\Core\Content\ImportExport\Struct\Progress;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@after-sales')]
class ImportExportAfterProcessFinishedEvent extends Event
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Context $context,
        private readonly ImportExportLogEntity $logEntity,
        private readonly Progress $progress
    ) {
    }

    public function getLogEntity(): ImportExportLogEntity
    {
        return $this->logEntity;
    }

    public function getProgress(): Progress
    {
        return $this->progress;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}

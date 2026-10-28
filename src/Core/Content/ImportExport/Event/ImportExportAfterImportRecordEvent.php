<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Event;

use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
#[Package('fundamentals@after-sales')]
class ImportExportAfterImportRecordEvent extends Event
{
    /**
     * @param array<string, mixed> $record
     * @param array<string, mixed> $row
     */
    public function __construct(
        private readonly EntityWrittenContainerEvent $result,
        private readonly array $record,
        private readonly array $row,
        private readonly Config $config,
        private readonly Context $context
    ) {
    }

    public function getResult(): EntityWrittenContainerEvent
    {
        return $this->result;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRecord(): array
    {
        return $this->record;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRow(): array
    {
        return $this->row;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}

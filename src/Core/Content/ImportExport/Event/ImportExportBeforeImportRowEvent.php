<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Event;

use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
class ImportExportBeforeImportRowEvent extends Event
{
    /**
     * @param array<string, mixed> $row
     */
    public function __construct(
        private array $row,
        private readonly Config $config,
        private readonly Context $context
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getRow(): array
    {
        return $this->row;
    }

    /**
     * @param array<string, mixed> $row
     */
    public function setRow(array $row): void
    {
        $this->row = $row;
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

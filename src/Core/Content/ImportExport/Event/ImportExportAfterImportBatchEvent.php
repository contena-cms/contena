<?php declare(strict_types=1);

namespace Contena\Core\Content\ImportExport\Event;

use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Content\ImportExport\Struct\ImportResult;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ImportExportAfterImportBatchEvent extends Event
{
    public function __construct(
        private readonly Config $config,
        private readonly Context $context,
        private readonly ImportResult $result,
    ) {
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getResult(): ImportResult
    {
        return $this->result;
    }
}

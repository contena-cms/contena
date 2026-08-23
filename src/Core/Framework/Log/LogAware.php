<?php declare(strict_types=1);

namespace Contena\Core\Framework\Log;

use Monolog\Level;
use Contena\Core\Framework\Event\IsFlowEventAware;

#[IsFlowEventAware]
interface LogAware
{
    /**
     * @return array<string, mixed>
     */
    public function getLogData(): array;

    public function getLogLevel(): Level;
}

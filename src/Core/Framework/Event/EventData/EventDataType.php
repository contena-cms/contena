<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event\EventData;

use Contena\Core\Framework\Log\Package;

interface EventDataType
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}

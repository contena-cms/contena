<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event\EventData;

interface EventDataType
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}

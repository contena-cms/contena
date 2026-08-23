<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig;

use Contena\Core\Framework\Context;

abstract class AbstractSystemConfigLoader
{
    abstract public function getDecorated(): AbstractSystemConfigLoader;

    /**
     * @return array<string, mixed>
     */
    abstract public function load(?string $channelId, ?Context $context = null): array;
}

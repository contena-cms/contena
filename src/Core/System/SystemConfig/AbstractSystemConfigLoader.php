<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig;


abstract class AbstractSystemConfigLoader
{
    abstract public function getDecorated(): AbstractSystemConfigLoader;

    /**
     * @return array<string, mixed>
     */
    abstract public function load(?string $channelId): array;
}

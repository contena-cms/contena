<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SystemConfig\Store\MemoizedSystemConfigStore;

class MemoizedSystemConfigLoader extends AbstractSystemConfigLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractSystemConfigLoader $decorated,
        private readonly MemoizedSystemConfigStore $memoizedSystemConfigStore
    ) {
    }

    public function getDecorated(): AbstractSystemConfigLoader
    {
        return $this->decorated;
    }

    public function load(?string $channelId): array
    {
        $config = $this->memoizedSystemConfigStore->getConfig($channelId);

        if ($config !== null) {
            return $config;
        }

        $config = $this->getDecorated()->load($channelId);
        $this->memoizedSystemConfigStore->setConfig($channelId, $config);

        return $config;
    }
}

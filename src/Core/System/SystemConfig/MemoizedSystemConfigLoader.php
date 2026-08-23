<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig;

use Contena\Core\Framework\Context;
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

    public function load(?string $channelId, ?Context $context = null): array
    {
        $contextKey = $this->getContextKey($context);
        $config = $this->memoizedSystemConfigStore->getConfig($channelId, $contextKey);

        if ($config !== null) {
            return $config;
        }

        $config = $this->getDecorated()->load($channelId, $context);
        $this->memoizedSystemConfigStore->setConfig($channelId, $contextKey, $config);

        return $config;
    }

    private function getContextKey(?Context $context): string
    {
        if ($context === null) {
            return 'implicit';
        }

        if ($context->getTenantId() !== null) {
            return 'tenant-' . $context->getTenantId();
        }

        return $context->hasGlobalTenantAccess() ? 'global' : 'platform';
    }
}

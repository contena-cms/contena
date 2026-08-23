<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig;

use Contena\Core\Framework\Context;

/**
 * @internal
 */
class ConfiguredSystemConfigLoader extends AbstractSystemConfigLoader
{
    public function __construct(
        private readonly AbstractSystemConfigLoader $decorated,
        private readonly SymfonySystemConfigService $config,
    ) {
    }

    public function getDecorated(): AbstractSystemConfigLoader
    {
        return $this->decorated;
    }

    public function load(?string $channelId, ?Context $context = null): array
    {
        $config = $this->decorated->load($channelId, $context);

        return $this->config->override($config, $channelId);
    }
}

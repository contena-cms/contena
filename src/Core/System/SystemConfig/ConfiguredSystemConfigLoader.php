<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig;


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

    public function load(?string $channelId): array
    {
        $config = $this->decorated->load($channelId);

        return $this->config->override($config, $channelId);
    }
}

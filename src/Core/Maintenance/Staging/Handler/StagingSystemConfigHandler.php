<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\Staging\Handler;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Contena\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
readonly class StagingSystemConfigHandler
{
    public function __construct(
        private SystemConfigService $systemConfigService
    ) {
    }

    public function __invoke(SetupStagingEvent $event): void
    {
        if ($event->systemConfigOverrides === []) {
            return;
        }

        foreach ($event->systemConfigOverrides as $scope => $config) {
            $channelId = $scope === 'default' ? null : $scope;

            foreach ($config as $key => $value) {
                $this->systemConfigService->set($key, $value, $channelId, true);

                if ($channelId === null) {
                    $event->io->info(\sprintf('Set system config "%s".', $key));
                } else {
                    $event->io->info(\sprintf('Set system config "%s" for sales channel "%s".', $key, $channelId));
                }
            }
        }
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\Staging\Handler;

use Doctrine\DBAL\Connection;
use Contena\Core\Maintenance\Staging\Event\SetupStagingEvent;

/**
 * @internal
 */
readonly class StagingAppHandler
{

    public function __invoke(SetupStagingEvent $event): void
    {
        $this->deleteAppsWithAppServer($event);

        $this->installationIdProvider->deleteInstallationId();
    }

    private function deleteAppsWithAppServer(SetupStagingEvent $event): void
    {
        $apps = $this->connection->fetchAllAssociative('SELECT id, integration_id, name FROM app WHERE app_secret IS NOT NULL');

        foreach ($apps as $app) {
            $this->connection->delete('app', ['id' => $app['id']]);
            $this->connection->delete('integration', ['id' => $app['integration_id']]);

            $event->io->info(\sprintf('Uninstalled app %s, install app again to establish a correct connection ', $app['name']));
        }
    }
}

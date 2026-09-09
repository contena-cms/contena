<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\InstallationIdChangeResolver;

use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\InstallationId\InstallationIdProvider;
use Contena\Core\Framework\App\Lifecycle\AppManager;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * @internal
 *
 * Resolver used when apps should be uninstalled
 * and the installationId should be regenerated, meaning the old shops and old apps work like before
 * apps in the current installation will be uninstalled without informing them about that (as they still run on the old installation)
 */
class UninstallAppsStrategy implements InstallationIdChangeStrategy
{
    final public const STRATEGY_NAME = 'uninstall-apps';

    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly InstallationIdProvider $installationIdProvider,
        private readonly AppManager $appManager,
    ) {
    }

    public function getName(): string
    {
        return self::STRATEGY_NAME;
    }

    public function getDescription(): string
    {
        return 'This is typically the right option if you have made a copy of your installation (e.g. a staging or testing environment) and you do not want to use the apps in this copy. Contena will delete the apps without notifying the app servers. A new installation identifier will be generated and the copied installation will identify as a new installation.';
    }

    public function resolve(Context $context): void
    {
        $this->installationIdProvider->deleteInstallationId();

        foreach ($this->appRepository->search(new Criteria(), $context)->getEntities() as $app) {
            // Delete the app locally only, to not inform the app server about the deactivation/deletion
            // The app is still running in the original installation with the same installationId.
            $this->appManager->delete($app, $context);
        }
    }
}

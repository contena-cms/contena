<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\InstallationIdChangeResolver;

use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\InstallationId\InstallationIdProvider;
use Contena\Core\Framework\App\Lifecycle\AppManager;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Psr\Log\LoggerInterface;

/**
 * @internal
 *
 * Resolver used when apps should be re-registered with a new installationId,
 * meaning the original installation and its apps continue to work like before,
 * while this copy registers as a brand-new installation at the app servers
 *
 * Will run through the registration process for all apps again
 * with the new appUrl and new installationId and replay the install lifecycle events for every app
 */
class ReinstallAppsStrategy implements InstallationIdChangeStrategy
{
    final public const string STRATEGY_NAME = 'reinstall-apps';

    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly AppManager $appManager,
        private readonly InstallationIdProvider $installationIdProvider,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getName(): string
    {
        return self::STRATEGY_NAME;
    }

    public function getDescription(): string
    {
        return 'This is typically the right option if you have made a copy of your installation (e.g. a staging or testing environment) and you want to use the apps in this copy. Contena will re-install the apps and register at the app servers using the new installation identifier. The copied installation will identify as a new installation.';
    }

    public function resolve(Context $context): void
    {
        $this->installationIdProvider->deleteInstallationId();

        // Re-registering contacts external app servers. If one app is unreachable we still want the
        // Let the remaining apps learn about the installation change, then report all failed apps together.
        /** @var list<string> $failedApps */
        $failedApps = [];

        foreach ($this->appRepository->search(new Criteria(), $context)->getEntities() as $app) {
            try {
                $this->appManager->reregister($app, $context);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to re-register app after installation ID change.', [
                    'appName' => $app->getName(),
                    'exception' => $e,
                ]);

                $failedApps[] = $app->getName();
            }
        }

        if ($failedApps !== []) {
            throw AppException::reinstallAppsFailed($failedApps);
        }
    }
}

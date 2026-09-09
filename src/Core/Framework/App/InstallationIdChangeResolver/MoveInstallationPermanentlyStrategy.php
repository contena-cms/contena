<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\InstallationIdChangeResolver;

use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Exception\InstallationIdChangeSuggestedException;
use Contena\Core\Framework\App\InstallationId\InstallationIdProvider;
use Contena\Core\Framework\App\Lifecycle\AppManager;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Psr\Log\LoggerInterface;

/**
 * @internal
 *
 * Resolver used when an installation is moved from one URL to another
 * and the installationId (and the data in the app backends associated with it) should be kept
 *
 * Will run through the registration process for all apps again
 * with the new appUrl so the apps can save the new URL and generate new Secrets
 * that way communication from the old installation to the app backend will be blocked in the future
 */
class MoveInstallationPermanentlyStrategy implements InstallationIdChangeStrategy
{
    final public const string STRATEGY_NAME = 'move-installation-permanently';

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
        return 'This is typically the right option if you have permanently moved your installation to different infrastructure or a new environment. Contena will notify apps (i.e. re-register at the app servers) using the same installation identifier and apps remain installed. The installation will identify as the same installation as before. This means that it will override the app data of the original installation.';
    }

    public function resolve(Context $context): void
    {
        try {
            $this->installationIdProvider->reset();
            $this->installationIdProvider->getInstallationId();

            // no resolution needed
            return;
        } catch (InstallationIdChangeSuggestedException $e) {
            $this->installationIdProvider->regenerateAndSetInstallationId($e->installationId->id);
        }

        // Refreshing the registration contacts external app servers. If one app is unreachable we
        // Still let the remaining apps learn about the installation change, then report all failed apps together.
        /** @var list<string> $failedApps */
        $failedApps = [];

        foreach ($this->appRepository->search(new Criteria(), $context)->getEntities() as $app) {
            try {
                $this->appManager->refreshRegistration($app, $context);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to re-register app after installation ID change.', [
                    'appName' => $app->getName(),
                    'exception' => $e,
                ]);

                $failedApps[] = $app->getName();
            }
        }

        if ($failedApps !== []) {
            throw AppException::installationMoveFailed($failedApps);
        }
    }
}

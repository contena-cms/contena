<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\ShopIdChangeResolver;

use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Lifecycle\AppManager;
use Contena\Core\Framework\App\ShopId\ShopIdProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Psr\Log\LoggerInterface;

/**
 * @internal
 *
 * Resolver used when apps should be re-registered with a new shopId,
 * meaning the old shop and its apps continue to work like before,
 * while this installation registers as a brand-new shop at the app servers
 *
 * Will run through the registration process for all apps again
 * with the new appUrl and new shopId and replay the install lifecycle events for every app
 */
class ReinstallAppsStrategy implements ShopIdChangeStrategy
{
    final public const string STRATEGY_NAME = 'reinstall-apps';

    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly EntityRepository $appRepository,
        private readonly AppManager $appManager,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getName(): string
    {
        return self::STRATEGY_NAME;
    }

    public function getDescription(): string
    {
        return 'This is typically the right option if you have made a copy of your shop (e.g. a staging or testing environment of a blogion shop) and you want to use the apps in this copy. Contena will re-install the apps and newly register at the app servers using the new shop identifier. Your shop will identify as a new shop.';
    }

    public function resolve(Context $context): void
    {
        $this->shopIdProvider->deleteShopId();

        // Re-registering contacts external app servers. If one app is unreachable we still want the
        // remaining apps to learn about the shop change, then report all failed apps together.
        /** @var list<string> $failedApps */
        $failedApps = [];

        foreach ($this->appRepository->search(new Criteria(), $context)->getEntities() as $app) {
            try {
                $this->appManager->reregister($app, $context);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to re-register app after shop ID change.', [
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

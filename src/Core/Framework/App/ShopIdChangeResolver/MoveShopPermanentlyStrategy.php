<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\ShopIdChangeResolver;

use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppException;
use Contena\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Contena\Core\Framework\App\Lifecycle\AppManager;
use Contena\Core\Framework\App\ShopId\ShopIdProvider;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Psr\Log\LoggerInterface;

/**
 * @internal
 *
 * Resolver used when shop is moved from one URL to another
 * and the shopId (and the data in the app backends associated with it) should be kept
 *
 * Will run through the registration process for all apps again
 * with the new appUrl so the apps can save the new URL and generate new Secrets
 * that way communication from the old shop to the app backend will be blocked in the future
 */
class MoveShopPermanentlyStrategy implements ShopIdChangeStrategy
{
    final public const string STRATEGY_NAME = 'move-shop-permanently';

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
        return 'This is typically the right option if you have permanently moved your shop to a different infrastructure or new environment. Contena will notify apps (i.e. re-register at the app servers) using the same shop identifier and apps remain installed. Your shop will identify as the same shop as before. This means, that this instance will override the app data of the original installation.';
    }

    public function resolve(Context $context): void
    {
        try {
            $this->shopIdProvider->reset();
            $this->shopIdProvider->getShopId();

            // no resolution needed
            return;
        } catch (ShopIdChangeSuggestedException $e) {
            $this->shopIdProvider->regenerateAndSetShopId($e->shopId->id);
        }

        // Refreshing the registration contacts external app servers. If one app is unreachable we
        // still want the remaining apps to learn about the shop change, then report all failed apps together.
        /** @var list<string> $failedApps */
        $failedApps = [];

        foreach ($this->appRepository->search(new Criteria(), $context)->getEntities() as $app) {
            try {
                $this->appManager->refreshRegistration($app, $context);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to re-register app after shop ID change.', [
                    'appName' => $app->getName(),
                    'exception' => $e,
                ]);

                $failedApps[] = $app->getName();
            }
        }

        if ($failedApps !== []) {
            throw AppException::shopMoveFailed($failedApps);
        }
    }
}

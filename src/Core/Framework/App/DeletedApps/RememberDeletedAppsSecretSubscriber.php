<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\DeletedApps;

use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\Event\AppDeletedEvent;
use Contena\Core\Framework\App\Event\AppInstalledEvent;
use Contena\Core\Framework\App\ShopId\ShopIdChangedEvent;
use Contena\Core\Framework\App\ShopId\ShopIdDeletedEvent;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
readonly class RememberDeletedAppsSecretSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private EntityRepository $appRepository,
        private DeletedAppsGateway $deletedAppsGateway,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            AppDeletedEvent::class => 'saveSecretFromDeletedApp',
            AppInstalledEvent::class => 'removeDeletedAppSecret',
            ShopIdChangedEvent::class => 'purgeOldSecrets',
            ShopIdDeletedEvent::class => 'purgeOldSecrets',
        ];
    }

    public function saveSecretFromDeletedApp(AppDeletedEvent $event): void
    {
        // The old secret is only needed to re-register an app that kept its data; otherwise a re-install starts fresh.
        if (!$event->keepUserData()) {
            return;
        }

        $criteria = new Criteria([$event->getAppId()]);
        $app = $this->appRepository->search($criteria, $event->getContext())->getEntities()->first();

        if (!$secret = $app?->getAppSecret()) {
            return;
        }

        // An app that adopted a candidate stops trusting the committed secret, so a reinstall needs both.
        $this->deletedAppsGateway->insertSecretsForDeletedApp(
            $app->getName(),
            $secret,
            $app->getUnconfirmedAppSecrets() ?? []
        );
    }

    public function removeDeletedAppSecret(AppInstalledEvent $event): void
    {
        $this->deletedAppsGateway->deleteSecretForApp($event->getApp()->getName());
    }

    /**
     * When the shopId changes, all current apps are re-registered
     * stored old secrets should be dismissed, as they are only valid when you re-install the app on the same shopId
     */
    public function purgeOldSecrets(): void
    {
        $this->deletedAppsGateway->purgeOldSecrets();
    }
}

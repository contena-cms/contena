<?php declare(strict_types=1);

namespace Contena\Core\Service\Subscriber;

use Contena\Core\Framework\App\AppCollection;
use Contena\Core\Framework\App\AppEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\Event\InstalledExtensionsListingLoadedEvent;
use Contena\Core\Framework\Store\Struct\ExtensionStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class InstalledExtensionsListingLoadedSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(private readonly EntityRepository $appRepository)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InstalledExtensionsListingLoadedEvent::class => 'removeAppsWithService',
        ];
    }

    /**
     * Remove apps from the listing which have an installed service equivalent
     */
    public function removeAppsWithService(InstalledExtensionsListingLoadedEvent $event): void
    {
        $existingServices = $this->appRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('selfManaged', true)),
            $event->context
        )->getEntities();

        $names = array_values($existingServices->map(static fn (AppEntity $app) => $app->getName()));

        $event->extensionCollection = $event->extensionCollection->filter(
            static fn (ExtensionStruct $ext) => !\in_array($ext->getName(), $names, true)
        );
    }
}

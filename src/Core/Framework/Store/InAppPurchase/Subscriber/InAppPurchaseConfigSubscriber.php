<?php declare(strict_types=1);

namespace Contena\Core\Framework\Store\InAppPurchase\Subscriber;

use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Contena\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseProvider;
use Contena\Core\Framework\Store\InAppPurchase\Services\InAppPurchaseUpdater;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Contena\Core\System\SystemConfig\Event\SystemConfigDomainLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('checkout')]
class InAppPurchaseConfigSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly InAppPurchaseUpdater $inAppPurchaseUpdater,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SystemConfigChangedEvent::class => 'updateIapKey',
            SystemConfigDomainLoadedEvent::class => 'removeIapInformationFromDomain',
        ];
    }

    public function updateIapKey(SystemConfigChangedEvent $event): void
    {
        if ($event->getKey() === StoreRequestOptionsProvider::CONFIG_KEY_STORE_SHOP_SECRET && $event->getValue() !== null) {
            $this->inAppPurchaseUpdater->update($this->getAdminContext() ?? Context::createDefaultContext());
        }
    }

    /**
     * We have to remove the IAP key from the system config domain,
     * otherwise it is exposed in the admin and the admin will overwrite it automatically,
     * thus circumventing our reset logic on license host change.
     */
    public function removeIapInformationFromDomain(SystemConfigDomainLoadedEvent $event): void
    {
        if ($event->getDomain() !== 'core.store.') {
            return;
        }

        $config = $event->getConfig();
        unset($config[InAppPurchaseProvider::CONFIG_STORE_IAP_KEY]);

        $event->setConfig($config);
    }

    private function getAdminContext(): ?Context
    {
        $context = $this->requestStack->getCurrentRequest()?->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        if ($context instanceof Context && $context->getSource() instanceof AdminApiSource) {
            return $context;
        }

        return null;
    }
}

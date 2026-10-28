<?php declare(strict_types=1);

namespace Contena\Storefront\Test\Controller;

use Contena\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Contena\Storefront\Event\StorefrontRenderEvent;
use Contena\Storefront\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class AuthTestSubscriber implements EventSubscriberInterface
{


    public static ?CustomerAccountRecoverRequestEvent $customerRecoveryEvent = null;

    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRenderEvent::class => 'onRender',
            AccountRecoverPasswordPageLoadedEvent::class => 'onPageLoad',
            CustomerAccountRecoverRequestEvent::EVENT_NAME => 'onRecoverEvent',
        ];
    }

    public function onRecoverEvent(CustomerAccountRecoverRequestEvent $event): void
    {
        self::$customerRecoveryEvent = $event;
    }


}

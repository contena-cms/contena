<?php declare(strict_types=1);

namespace Contena\Storefront\Test\Controller;

use Contena\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Contena\Storefront\Event\StorefrontRenderEvent;
use Contena\Storefront\Page\Account\RecoverPassword\AccountRecoverPasswordPage;
use Contena\Storefront\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class AuthTestSubscriber implements EventSubscriberInterface
{
    public static ?StorefrontRenderEvent $renderEvent = null;

    public static ?AccountRecoverPasswordPage $page = null;

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

    public function onRender(StorefrontRenderEvent $event): void
    {
        $skippedViews = [
            '@Storefront/storefront/layout/header.html.twig',
            '@Storefront/storefront/layout/footer.html.twig',
        ];
        if (\in_array($event->getView(), $skippedViews, true)) {
            return;
        }

        self::$renderEvent = $event;
    }

    public function onPageLoad(AccountRecoverPasswordPageLoadedEvent $event): void
    {
        self::$page = $event->getPage();
    }
}

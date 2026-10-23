<?php declare(strict_types=1);

namespace Contena\Frontend\Test\Controller;

use Contena\Core\System\User\Event\UserAccountRecoverRequestEvent;
use Contena\Frontend\Event\FrontendRenderEvent;
use Contena\Frontend\Page\Account\RecoverPassword\AccountRecoverPasswordPage;
use Contena\Frontend\Page\Account\RecoverPassword\AccountRecoverPasswordPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class AuthTestSubscriber implements EventSubscriberInterface
{
    public static ?FrontendRenderEvent $renderEvent = null;

    public static ?AccountRecoverPasswordPage $page = null;

    public static ?UserAccountRecoverRequestEvent $userRecoveryEvent = null;

    public static function getSubscribedEvents(): array
    {
        return [
            FrontendRenderEvent::class => 'onRender',
            AccountRecoverPasswordPageLoadedEvent::class => 'onPageLoad',
            UserAccountRecoverRequestEvent::EVENT_NAME => 'onRecoverEvent',
        ];
    }

    public function onRecoverEvent(UserAccountRecoverRequestEvent $event): void
    {
        self::$userRecoveryEvent = $event;
    }

    public function onRender(FrontendRenderEvent $event): void
    {
        $skippedViews = [
            '@Frontend/frontend/layout/header.html.twig',
            '@Frontend/frontend/layout/footer.html.twig',
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

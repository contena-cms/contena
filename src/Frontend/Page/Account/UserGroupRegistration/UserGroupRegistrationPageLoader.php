<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\UserGroupRegistration;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Channel\AbstractUserGroupRegistrationSettingsRoute;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
class UserGroupRegistrationPageLoader extends AbstractUserGroupRegistrationPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AccountLoginPageLoader $accountLoginPageLoader,
        private readonly AbstractUserGroupRegistrationSettingsRoute $userGroupRegistrationRoute,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function load(Request $request, ChannelContext $channelContext): UserGroupRegistrationPage
    {
        $page = UserGroupRegistrationPage::createFrom($this->accountLoginPageLoader->load($request, $channelContext));
        $userGroupId = $request->attributes->get('userGroupId');

        $page->setGroup(
            $this->userGroupRegistrationRoute->load($userGroupId, $channelContext)->getRegistration()
        );

        if ($page->getMetaInformation()) {
            $metaDescription = $page->getGroup()->getTranslation('registrationSeoMetaDescription');
            if ($metaDescription) {
                $page->getMetaInformation()->setMetaDescription($metaDescription);
            }

            $title = $page->getGroup()->getTranslation('registrationTitle');
            if ($title) {
                $page->getMetaInformation()->setMetaTitle($title);
            }
        }

        $this->eventDispatcher->dispatch(new UserGroupRegistrationPageLoadedEvent($page, $channelContext, $request));

        return $page;
    }

    public function getDecorated(): AbstractUserGroupRegistrationPageLoader
    {
        throw new DecorationPatternException(self::class);
    }
}

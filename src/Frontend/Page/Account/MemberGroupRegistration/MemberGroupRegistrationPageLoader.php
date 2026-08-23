<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\MemberGroupRegistration;

use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\Channel\AbstractMemberGroupRegistrationSettingsRoute;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a channel-api route to get or put data.
 */
class MemberGroupRegistrationPageLoader extends AbstractMemberGroupRegistrationPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AccountLoginPageLoader $accountLoginPageLoader,
        private readonly AbstractMemberGroupRegistrationSettingsRoute $memberGroupRegistrationRoute,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function load(Request $request, ChannelContext $channelContext): MemberGroupRegistrationPage
    {
        $page = MemberGroupRegistrationPage::createFrom($this->accountLoginPageLoader->load($request, $channelContext));
        $memberGroupId = $request->attributes->get('memberGroupId');

        $page->setGroup(
            $this->memberGroupRegistrationRoute->load($memberGroupId, $channelContext)->getRegistration(),
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

        $this->eventDispatcher->dispatch(new MemberGroupRegistrationPageLoadedEvent($page, $channelContext, $request));

        return $page;
    }

    public function getDecorated(): AbstractMemberGroupRegistrationPageLoader
    {
        throw new DecorationPatternException(self::class);
    }
}

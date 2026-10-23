<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\Profile;

use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\MetaInformation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
class AccountProfilePageLoader
{

    /**
     * @throws CategoryNotFoundException
     * @throws UserNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, ChannelContext $channelContext): AccountProfilePage
    {
        if ($channelContext->getUser() === null) {
            throw CartException::userNotLoggedIn();
        }

        $page = $this->genericLoader->load($request, $channelContext);

        $page = AccountProfilePage::createFrom($page);
        $this->setMetaInformation($page);

        $page->setSalutations($this->getSalutations($channelContext, $request));

        $this->eventDispatcher->dispatch(
            new AccountProfilePageLoadedEvent($page, $channelContext, $request)
        );

        return $page;
    }

    protected function setMetaInformation(AccountProfilePage $page): void
    {
        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        if ($page->getMetaInformation() === null) {
            $page->setMetaInformation(new MetaInformation());
        }

        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans('account.profileMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
        );
    }

}

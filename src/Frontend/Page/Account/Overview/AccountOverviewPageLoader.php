<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\Overview;

use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\Channel\AbstractMemberRoute;
use Contena\Core\System\Member\MemberEntity;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Contena\Frontend\Page\MetaInformation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a channel-api route to get or put data.
 */
class AccountOverviewPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractMemberRoute $memberRoute,
        private readonly AbstractTranslator $translator,
    ) {
    }

    public function load(Request $request, ChannelContext $channelContext, MemberEntity $member): AccountOverviewPage
    {
        $page = $this->genericLoader->load($request, $channelContext);

        $page = AccountOverviewPage::createFrom($page);
        $page->setMember($this->loadMember($channelContext, $member));
        $this->setMetaInformation($page);

        $this->eventDispatcher->dispatch(
            new AccountOverviewPageLoadedEvent($page, $channelContext, $request),
        );

        return $page;
    }

    protected function setMetaInformation(AccountOverviewPage $page): void
    {
        $page->getMetaInformation()?->setRobots('noindex,follow');

        if ($page->getMetaInformation() === null) {
            $page->setMetaInformation(new MetaInformation());
        }

        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans('account.overviewMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle(),
        );
    }

    private function loadMember(ChannelContext $context, MemberEntity $member): MemberEntity
    {
        $criteria = new Criteria();
        $criteria->addAssociation('requestedGroup');
        $criteria->addAssociation('addresses.country');
        $criteria->addAssociation('addresses.region');

        return $this->memberRoute->load(new Request(), $context, $criteria, $member)->getMember();
    }
}

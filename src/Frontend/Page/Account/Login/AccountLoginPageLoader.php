<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\Login;

use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Country\Channel\AbstractCountryRoute;
use Contena\Core\System\Country\CountryCollection;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Contena\Frontend\Page\MetaInformation;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a channel-api route to get or put data.
 */
class AccountLoginPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractCountryRoute $countryRoute,
        private readonly AbstractTranslator $translator,
    ) {
    }

    public function load(Request $request, ChannelContext $channelContext): AccountLoginPage
    {
        $page = $this->genericLoader->load($request, $channelContext);

        $page = AccountLoginPage::createFrom($page);
        $this->setMetaInformation($page);

        $page->setCountries($this->getCountries($channelContext));

        $this->eventDispatcher->dispatch(
            new AccountLoginPageLoadedEvent($page, $channelContext, $request),
        );

        return $page;
    }

    protected function setMetaInformation(AccountLoginPage $page): void
    {
        $page->getMetaInformation()?->setRobots('noindex,follow');

        if ($page->getMetaInformation() === null) {
            $page->setMetaInformation(new MetaInformation());
        }

        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans('account.loginMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle(),
        );
    }

    private function getCountries(ChannelContext $channelContext): CountryCollection
    {
        $criteria = new Criteria()
            ->addSorting(new FieldSorting('position', FieldSorting::ASCENDING))
            ->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        return $this->countryRoute->load(new Request(), $criteria, $channelContext)->getCountries();
    }
}

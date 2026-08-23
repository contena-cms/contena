<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Address\Listing;

use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Country\Channel\AbstractCountryRoute;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Member\Channel\AbstractListAddressRoute;
use Contena\Core\System\Member\MemberEntity;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AddressListingPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly AbstractCountryRoute $countryRoute,
        private readonly AbstractListAddressRoute $listAddressRoute,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractTranslator $translator,
    ) {
    }

    public function load(Request $request, ChannelContext $channelContext, MemberEntity $member): AddressListingPage
    {
        $page = $this->genericLoader->load($request, $channelContext);

        $page = AddressListingPage::createFrom($page);
        $this->setMetaInformation($page);
        $page->setCountries($this->getCountries($channelContext));

        $criteria = new Criteria()
            ->addAssociation('country')
            ->addAssociation('region')
            ->addSorting(new FieldSorting('firstName', FieldSorting::ASCENDING));

        $page->setAddresses($this->listAddressRoute->load($criteria, $channelContext, $member)->getAddressCollection());
        $page->setAddress($page->getAddresses()->get(RequestParamHelper::get($request, 'addressId')));

        $this->eventDispatcher->dispatch(
            new AddressListingPageLoadedEvent($page, $channelContext, $request),
        );

        return $page;
    }

    protected function setMetaInformation(AddressListingPage $page): void
    {
        $page->getMetaInformation()?->setRobots('noindex,follow');
        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans('account.addressMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle(),
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

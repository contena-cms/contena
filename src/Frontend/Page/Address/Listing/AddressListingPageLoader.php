<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Address\Listing;

use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\User\UserEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class AddressListingPageLoader
{

    /**
     * @throws CategoryNotFoundException
     * @throws UserNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, ChannelContext $channelContext, UserEntity $user): AddressListingPage
    {
        $page = $this->genericLoader->load($request, $channelContext);

        $page = AddressListingPage::createFrom($page);
        $this->setMetaInformation($page);

        $page->setSalutations($this->getSalutations($channelContext));

        $page->setCountries($this->getCountries($channelContext));

        $criteria = (new Criteria())->addSorting(new FieldSorting('firstName', FieldSorting::ASCENDING));

        $page->setAddresses($this->listAddressRoute->load($criteria, $channelContext, $user)->getAddressCollection());

        $page->setCart($this->cartService->getCart($channelContext->getToken(), $channelContext));

        $page->setAddress(
            $page->getAddresses()->get(RequestParamHelper::get($request, 'addressId'))
        );

        $this->eventDispatcher->dispatch(
            new AddressListingPageLoadedEvent($page, $channelContext, $request)
        );

        return $page;
    }

    protected function setMetaInformation(AddressListingPage $page): void
    {
        $page->getMetaInformation()?->setRobots('noindex,follow');
        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans('account.addressMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
        );
    }


    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getCountries(ChannelContext $channelContext): CountryCollection
    {
        $criteria = (new Criteria())
            ->addSorting(new FieldSorting('position', FieldSorting::ASCENDING))
            ->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $criteria->getAssociation('states')
            ->addSorting(new FieldSorting('position', FieldSorting::ASCENDING))
            ->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        return $this->countryRoute
            ->load(new Request(), $criteria, $channelContext)
            ->getCountries();
    }
}

<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Address\Detail;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Uuid\UuidException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
class AddressDetailPageLoader
{

    /**
     * @throws AddressNotFoundException
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws InvalidUuidException
     * @throws RoutingException
     */
    public function load(Request $request, ChannelContext $channelContext, UserEntity $user): AddressDetailPage
    {
        $page = $this->genericLoader->load($request, $channelContext);

        $page = AddressDetailPage::createFrom($page);
        $this->setMetaInformation($page, $request);

        $page->setSalutations($this->getSalutations($channelContext));

        $page->setCountries($this->getCountries($channelContext));

        $page->setAddress($this->getAddress($request, $channelContext, $user));

        $this->eventDispatcher->dispatch(
            new AddressDetailPageLoadedEvent($page, $channelContext, $request)
        );

        return $page;
    }

    protected function setMetaInformation(AddressDetailPage $page, Request $request): void
    {
        $page->getMetaInformation()?->setRobots('noindex,follow');

        if ($request->attributes->get('_route') === 'frontend.account.address.create.page') {
            $page->getMetaInformation()?->setMetaTitle(
                $this->translator->trans('account.addressCreateMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
            );
        } elseif ($request->attributes->get('_route') === 'frontend.account.address.edit.page') {
            $page->getMetaInformation()?->setMetaTitle(
                $this->translator->trans('account.addressEditMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
            );
        }
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

        return $this->countryRoute->load(new Request(), $criteria, $channelContext)->getCountries();
    }

    /**
     * @throws AddressNotFoundException
     * @throws InvalidUuidException
     */
    private function getAddress(Request $request, ChannelContext $context, UserEntity $user): ?UserAddressEntity
    {
        if (!$addressId = $request->attributes->getString('addressId')) {
            return null;
        }

        if (!Uuid::isValid($addressId)) {
            throw UuidException::invalidUuid($addressId);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $addressId));
        $criteria->addFilter(new EqualsFilter('userId', $user->getId()));

        $address = $this->listAddressRoute->load($criteria, $context, $user)->getAddressCollection()->get($addressId);

        if (!$address) {
            throw UserException::addressNotFound($addressId);
        }

        return $address;
    }
}

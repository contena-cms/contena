<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Address\Detail;

use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Uuid\UuidException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Country\Channel\AbstractCountryRoute;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Member\Aggregate\MemberAddress\MemberAddressEntity;
use Contena\Core\System\Member\Channel\AbstractListAddressRoute;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\MemberException;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a channel-api route to get or put data.
 */
class AddressDetailPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly AbstractCountryRoute $countryRoute,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractListAddressRoute $listAddressRoute,
        private readonly AbstractTranslator $translator,
    ) {
    }

    public function load(Request $request, ChannelContext $channelContext, MemberEntity $member): AddressDetailPage
    {
        $page = $this->genericLoader->load($request, $channelContext);

        $page = AddressDetailPage::createFrom($page);
        $this->setMetaInformation($page, $request);
        $page->setCountries($this->getCountries($channelContext));
        $page->setAddress($this->getAddress($request, $channelContext, $member));

        $this->eventDispatcher->dispatch(
            new AddressDetailPageLoadedEvent($page, $channelContext, $request),
        );

        return $page;
    }

    protected function setMetaInformation(AddressDetailPage $page, Request $request): void
    {
        $page->getMetaInformation()?->setRobots('noindex,follow');

        if ($request->attributes->get('_route') === 'frontend.account.address.create.page') {
            $page->getMetaInformation()?->setMetaTitle(
                $this->translator->trans('account.addressCreateMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle(),
            );
        } elseif ($request->attributes->get('_route') === 'frontend.account.address.edit.page') {
            $page->getMetaInformation()?->setMetaTitle(
                $this->translator->trans('account.addressEditMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle(),
            );
        }
    }

    private function getCountries(ChannelContext $channelContext): CountryCollection
    {
        $criteria = new Criteria()
            ->addSorting(new FieldSorting('position', FieldSorting::ASCENDING))
            ->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        return $this->countryRoute->load(new Request(), $criteria, $channelContext)->getCountries();
    }

    private function getAddress(Request $request, ChannelContext $context, MemberEntity $member): ?MemberAddressEntity
    {
        if (!$addressId = $request->attributes->getString('addressId')) {
            return null;
        }

        if (!Uuid::isValid($addressId)) {
            throw UuidException::invalidUuid($addressId);
        }

        $criteria = new Criteria();
        $criteria->addAssociation('country');
        $criteria->addAssociation('region');
        $criteria->addFilter(new EqualsFilter('id', $addressId));
        $criteria->addFilter(new EqualsFilter('memberId', $member->getId()));

        $address = $this->listAddressRoute->load($criteria, $context, $member)->getAddressCollection()->get($addressId);

        if (!$address) {
            throw MemberException::addressNotFound($addressId);
        }

        return $address;
    }
}

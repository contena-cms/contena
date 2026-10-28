<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Contena\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Checkout\Customer\Event\AddressListingCriteriaEvent;
use Contena\Core\Checkout\Customer\Extension\ListAddressRouteExtension;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ListAddressRoute extends AbstractListAddressRoute
{
    /**
     * @internal
     *
     * @param SalesChannelRepository<CustomerAddressCollection> $addressRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $addressRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractListAddressRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/account/list-address',
        name: 'store-api.account.address.list.get',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
            PlatformRequest::ATTRIBUTE_ENTITY => CustomerAddressDefinition::ENTITY_NAME,
        ],
        methods: [Request::METHOD_GET, Request::METHOD_POST],
    )]
    public function load(Criteria $criteria, SalesChannelContext $context, CustomerEntity $customer): ListAddressRouteResponse
    {
        return $this->extensions->publish(
            name: ListAddressRouteExtension::NAME,
            extension: new ListAddressRouteExtension($criteria, $context, $customer),
            function: fn (Criteria $criteria, SalesChannelContext $context, CustomerEntity $customer): ListAddressRouteResponse => $this->_load($criteria, $context),
        );
    }

    private function _load(Criteria $criteria, SalesChannelContext $context): ListAddressRouteResponse
    {
        $criteria
            ->addAssociation('salutation')
            ->addAssociation('country')
            ->addAssociation('countryState');

        $this->eventDispatcher->dispatch(new AddressListingCriteriaEvent($criteria, $context));

        return new ListAddressRouteResponse($this->addressRepository->search($criteria, $context));
    }
}

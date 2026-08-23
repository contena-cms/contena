<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Member\Event\AddressListingCriteriaEvent;
use Contena\Core\System\Member\MemberEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class ListAddressRoute extends AbstractListAddressRoute
{
    /**
     * @internal
     *
     * @param ChannelRepository<ChannelMemberAddressCollection> $addressRepository
     */
    public function __construct(
        private readonly ChannelRepository $addressRepository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getDecorated(): AbstractListAddressRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/account/list-address',
        name: 'channel-api.account.address.list.get',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
            PlatformRequest::ATTRIBUTE_ENTITY => ChannelMemberAddressDefinition::ENTITY_NAME,
        ],
        methods: [Request::METHOD_GET, Request::METHOD_POST],
    )]
    public function load(Criteria $criteria, ChannelContext $context, MemberEntity $member): ListAddressRouteResponse
    {
        $criteria
            ->addAssociation('country')
            ->addAssociation('region');

        $this->eventDispatcher->dispatch(new AddressListingCriteriaEvent($criteria, $context));

        return new ListAddressRouteResponse($this->addressRepository->search($criteria, $context));
    }
}

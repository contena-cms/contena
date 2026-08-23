<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\NoContentResponse;
use Contena\Core\System\Member\Aggregate\MemberAddress\MemberAddressCollection;
use Contena\Core\System\Member\MemberEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class DeleteAddressRoute extends AbstractDeleteAddressRoute
{
    use MemberAddressValidationTrait;

    /**
     * @param EntityRepository<MemberAddressCollection> $addressRepository
     *
     * @internal
     */
    public function __construct(private readonly EntityRepository $addressRepository)
    {
    }

    public function getDecorated(): AbstractDeleteAddressRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/account/address/{addressId}',
        name: 'channel-api.account.address.delete',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_DELETE]
    )]
    public function delete(string $addressId, ChannelContext $context, MemberEntity $member): NoContentResponse
    {
        $this->validateAddress($addressId, $context, $member);

        $this->addressRepository->delete([['id' => $addressId]], $context->getContext());

        return new NoContentResponse();
    }
}

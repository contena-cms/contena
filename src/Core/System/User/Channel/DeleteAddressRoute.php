<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\NoContentResponse;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressCollection;
use Contena\Core\System\User\Extension\DeleteAddressRouteExtension;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class DeleteAddressRoute extends AbstractDeleteAddressRoute
{
    use UserAddressValidationTrait;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

    /**
     * @param EntityRepository<UserAddressCollection> $addressRepository
     *
     * @internal
     */
    public function __construct(private readonly EntityRepository $addressRepository, private readonly ExtensionDispatcher $extensions)
    {
    }

    public function getDecorated(): AbstractDeleteAddressRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/account/address/{addressId}',
        name: 'store-api.account.address.delete',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_DELETE]
    )]
    public function delete(string $addressId, ChannelContext $context, UserEntity $user): NoContentResponse
    {
        return $this->extensions->publish(
            name: DeleteAddressRouteExtension::NAME,
            extension: new DeleteAddressRouteExtension($addressId, $context, $user),
            function: $this->_delete(...),
        );
    }

    private function _delete(string $addressId, ChannelContext $context, UserEntity $user): NoContentResponse
    {
        $this->validateAddress($addressId, $context, $user);

        if (
            $addressId === $user->getDefaultBillingAddressId()
            || $addressId === $user->getDefaultShippingAddressId()
        ) {
            throw UserException::cannotDeleteDefaultAddress($addressId);
        }

        $this->addressRepository->delete([['id' => $addressId]], $context->getContext());

        if ($addressId === $user->getActiveBillingAddress()?->getId()) {
            /** @deprecated tag:v6.8.0 - Use setter instead */
            $user->assign(['activeBillingAddress' => $user->getDefaultBillingAddress()]);
            // $user->setActiveBillingAddress($user->getDefaultBillingAddress());
        }

        if ($addressId === $user->getActiveShippingAddress()?->getId()) {
            /** @deprecated tag:v6.8.0 - Use setter instead */
            $user->assign(['activeShippingAddress' => $user->getDefaultShippingAddress()]);
            // $user->setActiveShippingAddress($user->getDefaultShippingAddress());
        }

        return new NoContentResponse();
    }
}

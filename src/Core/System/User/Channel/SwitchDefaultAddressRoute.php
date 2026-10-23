<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\NoContentResponse;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressCollection;
use Contena\Core\System\User\Event\UserSetDefaultBillingAddressEvent;
use Contena\Core\System\User\Event\UserSetDefaultShippingAddressEvent;
use Contena\Core\System\User\Extension\SwitchDefaultAddressRouteExtension;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\User\Channel\UserAddressValidationTrait;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class SwitchDefaultAddressRoute extends AbstractSwitchDefaultAddressRoute
{
    use UserAddressValidationTrait;

    /**
     * @internal
     *
     * @param EntityRepository<UserAddressCollection> $addressRepository
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private readonly EntityRepository $addressRepository,
        private readonly EntityRepository $userRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractSwitchDefaultAddressRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/account/address/default-shipping/{addressId}',
        name: 'channel-api.account.address.change.default.shipping',
        defaults: [
            'type' => 'shipping',
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_PATCH]
    )]
    #[Route(
        path: '/channel-api/account/address/default-billing/{addressId}',
        name: 'channel-api.account.address.change.default.billing',
        defaults: [
            'type' => 'billing',
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_PATCH]
    )]
    public function swap(string $addressId, string $type, ChannelContext $context, UserEntity $user): NoContentResponse
    {
        return $this->extensions->publish(
            name: SwitchDefaultAddressRouteExtension::NAME,
            extension: new SwitchDefaultAddressRouteExtension($addressId, $type, $context, $user),
            function: $this->_swap(...),
        );
    }

    private function _swap(string $addressId, string $type, ChannelContext $context, UserEntity $user): NoContentResponse
    {
        $this->validateAddress($addressId, $context, $user);

        switch ($type) {
            case self::TYPE_BILLING:
                $data = [
                    'id' => $user->getId(),
                    'defaultBillingAddressId' => $addressId,
                ];

                $event = new UserSetDefaultBillingAddressEvent($context, $user, $addressId);
                $this->eventDispatcher->dispatch($event);

                break;
            default:
                $data = [
                    'id' => $user->getId(),
                    'defaultShippingAddressId' => $addressId,
                ];

                $event = new UserSetDefaultShippingAddressEvent($context, $user, $addressId);
                $this->eventDispatcher->dispatch($event);

                break;
        }

        $this->userRepository->update([$data], $context->getContext());

        return new NoContentResponse();
    }
}

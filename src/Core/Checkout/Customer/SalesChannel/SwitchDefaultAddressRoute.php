<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Contena\Core\Checkout\Customer\CustomerCollection;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Checkout\Customer\Event\CustomerSetDefaultBillingAddressEvent;
use Contena\Core\Checkout\Customer\Event\CustomerSetDefaultShippingAddressEvent;
use Contena\Core\Checkout\Customer\Extension\SwitchDefaultAddressRouteExtension;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SalesChannel\NoContentResponse;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class SwitchDefaultAddressRoute extends AbstractSwitchDefaultAddressRoute
{
    use CustomerAddressValidationTrait;

    /**
     * @internal
     *
     * @param EntityRepository<CustomerAddressCollection> $addressRepository
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    public function __construct(
        private readonly EntityRepository $addressRepository,
        private readonly EntityRepository $customerRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractSwitchDefaultAddressRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/account/address/default-shipping/{addressId}',
        name: 'store-api.account.address.change.default.shipping',
        defaults: [
            'type' => 'shipping',
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_PATCH]
    )]
    #[Route(
        path: '/store-api/account/address/default-billing/{addressId}',
        name: 'store-api.account.address.change.default.billing',
        defaults: [
            'type' => 'billing',
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_PATCH]
    )]
    public function swap(string $addressId, string $type, SalesChannelContext $context, CustomerEntity $customer): NoContentResponse
    {
        return $this->extensions->publish(
            name: SwitchDefaultAddressRouteExtension::NAME,
            extension: new SwitchDefaultAddressRouteExtension($addressId, $type, $context, $customer),
            function: $this->_swap(...),
        );
    }

    private function _swap(string $addressId, string $type, SalesChannelContext $context, CustomerEntity $customer): NoContentResponse
    {
        $this->validateAddress($addressId, $context, $customer);

        switch ($type) {
            case self::TYPE_BILLING:
                $data = [
                    'id' => $customer->getId(),
                    'defaultBillingAddressId' => $addressId,
                ];

                $event = new CustomerSetDefaultBillingAddressEvent($context, $customer, $addressId);
                $this->eventDispatcher->dispatch($event);

                break;
            default:
                $data = [
                    'id' => $customer->getId(),
                    'defaultShippingAddressId' => $addressId,
                ];

                $event = new CustomerSetDefaultShippingAddressEvent($context, $customer, $addressId);
                $this->eventDispatcher->dispatch($event);

                break;
        }

        $this->customerRepository->update([$data], $context->getContext());

        return new NoContentResponse();
    }
}

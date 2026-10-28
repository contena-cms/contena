<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupCollection;
use Contena\Core\Checkout\Customer\CustomerException;
use Contena\Core\Checkout\Customer\Extension\CustomerGroupRegistrationSettingsRouteExtension;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Attribute\Route;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class CustomerGroupRegistrationSettingsRoute extends AbstractCustomerGroupRegistrationSettingsRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<CustomerGroupCollection> $customerGroupRepository
     */
    public function __construct(private readonly EntityRepository $customerGroupRepository, private readonly ExtensionDispatcher $extensions)
    {
    }

    public function getDecorated(): AbstractCustomerGroupRegistrationSettingsRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * Though this is a GET route, caching was not added as the output may be altered depending on dynamic rules,
     * which is not taken into account during the cache hash calculation.
     */
    #[Route(path: '/store-api/customer-group-registration/config/{customerGroupId}', name: 'store-api.customer-group-registration.config', methods: ['GET'])]
    public function load(string $customerGroupId, SalesChannelContext $context): CustomerGroupRegistrationSettingsRouteResponse
    {
        return $this->extensions->publish(
            name: CustomerGroupRegistrationSettingsRouteExtension::NAME,
            extension: new CustomerGroupRegistrationSettingsRouteExtension($customerGroupId, $context),
            function: $this->_load(...),
        );
    }

    private function _load(string $customerGroupId, SalesChannelContext $context): CustomerGroupRegistrationSettingsRouteResponse
    {
        $criteria = (new Criteria([$customerGroupId]))
            ->addFilter(new EqualsFilter('registrationActive', 1))
            ->addFilter(new EqualsFilter('registrationSalesChannels.id', $context->getSalesChannelId()));

        $result = $this->customerGroupRepository->search($criteria, $context->getContext());
        if ($result->getTotal() === 0) {
            throw CustomerException::customerGroupRegistrationConfigurationNotFound($customerGroupId);
        }

        $customerGroup = $result->getEntities()->first();
        \assert($customerGroup !== null);

        return new CustomerGroupRegistrationSettingsRouteResponse($customerGroup);
    }
}

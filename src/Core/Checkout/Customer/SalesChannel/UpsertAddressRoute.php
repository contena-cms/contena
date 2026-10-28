<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Contena\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Checkout\Customer\CustomerEvents;
use Contena\Core\Checkout\Customer\Extension\UpsertAddressRouteExtension;
use Contena\Core\Checkout\Customer\Validation\Constraint\CustomerZipCode;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Event\DataMappingEvent;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Contena\Core\System\Salutation\SalutationDefinition;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class UpsertAddressRoute extends AbstractUpsertAddressRoute
{
    use CustomerAddressDataNormalizerTrait;
    use CustomerAddressValidationTrait;

    /**
     * @internal
     *
     * @param EntityRepository<CustomerAddressCollection> $addressRepository
     * @param SalesChannelRepository<CustomerAddressCollection> $salesChannelAddressRepository
     * @param EntityRepository<SalutationCollection> $salutationRepository
     */
    public function __construct(
        private readonly EntityRepository $addressRepository,
        private readonly SalesChannelRepository $salesChannelAddressRepository,
        private readonly DataValidator $validator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidationFactoryInterface $addressValidationFactory,
        private readonly SystemConfigService $systemConfigService,
        private readonly StoreApiCustomFieldMapper $storeApiCustomFieldMapper,
        private readonly EntityRepository $salutationRepository,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractUpsertAddressRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/account/address',
        name: 'store-api.account.address.create',
        defaults: [
            'addressId' => null,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    #[Route(
        path: '/store-api/account/address/{addressId}',
        name: 'store-api.account.address.update',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_PATCH]
    )]
    public function upsert(
        ?string $addressId,
        RequestDataBag $data,
        SalesChannelContext $context,
        CustomerEntity $customer
    ): UpsertAddressRouteResponse {
        return $this->extensions->publish(
            name: UpsertAddressRouteExtension::NAME,
            extension: new UpsertAddressRouteExtension($addressId, $data, $context, $customer),
            function: $this->_upsert(...),
        );
    }

    private function _upsert(
        ?string $addressId,
        RequestDataBag $data,
        SalesChannelContext $context,
        CustomerEntity $customer
    ): UpsertAddressRouteResponse {
        if (!$addressId) {
            $isCreate = true;
            $addressId = Uuid::randomHex();
        } else {
            $this->validateAddress($addressId, $context, $customer);
            $isCreate = false;
        }

        if (!$data->get('salutationId')) {
            $data->set('salutationId', $this->getDefaultSalutationId($context));
        }

        $accountType = $data->get('accountType', CustomerEntity::ACCOUNT_TYPE_PRIVATE);
        $definition = $this->getValidationDefinition($data, $accountType, $isCreate, $context);
        $this->validator->validate(array_merge(['id' => $addressId], $data->all()), $definition);

        $addressData = [
            'salutationId' => $data->get('salutationId'),
            'firstName' => $data->get('firstName'),
            'lastName' => $data->get('lastName'),
            'street' => $data->get('street'),
            'city' => $data->get('city'),
            'zipcode' => $data->get('zipcode'),
            'countryId' => $data->get('countryId'),
            'countryStateId' => $data->get('countryStateId') ?: null,
            'company' => $data->get('company'),
            'department' => $data->get('department'),
            'title' => $data->get('title'),
            'phoneNumber' => $data->get('phoneNumber'),
            'additionalAddressLine1' => $data->get('additionalAddressLine1'),
            'additionalAddressLine2' => $data->get('additionalAddressLine2'),
        ];

        $addressData = $this->trimAddressFields($addressData);

        if ($data->get('customFields') instanceof RequestDataBag) {
            $addressData['customFields'] = $this->storeApiCustomFieldMapper->map(
                CustomerAddressDefinition::ENTITY_NAME,
                $data->get('customFields')
            );
            if ($addressData['customFields'] === []) {
                unset($addressData['customFields']);
            }
        }

        $mappingEvent = new DataMappingEvent($data, $addressData, $context->getContext());
        $this->eventDispatcher->dispatch($mappingEvent, CustomerEvents::MAPPING_ADDRESS_CREATE);

        $addressData = $mappingEvent->getOutput();
        $addressData['id'] = $addressId;
        $addressData['customerId'] = $customer->getId();

        $this->addressRepository->upsert([$addressData], $context->getContext());

        $address = $this->salesChannelAddressRepository->search(new Criteria([$addressId]), $context)->getEntities()->first();
        \assert($address !== null);

        return new UpsertAddressRouteResponse($address);
    }

    private function getValidationDefinition(
        DataBag $data,
        string $accountType,
        bool $isCreate,
        SalesChannelContext $context
    ): DataValidationDefinition {
        if ($isCreate) {
            $validation = $this->addressValidationFactory->create($context);
        } else {
            $validation = $this->addressValidationFactory->update($context);
        }

        if ($accountType === CustomerEntity::ACCOUNT_TYPE_BUSINESS
            && $this->systemConfigService->get('core.loginRegistration.showAccountTypeSelection')
        ) {
            $validation->add('company', new NotBlank());
        }

        $validation->set('zipcode', new CustomerZipCode(countryId: $data->get('countryId')));

        $validationEvent = new BuildValidationEvent($validation, $data, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        return $validation;
    }

    private function getDefaultSalutationId(SalesChannelContext $context): ?string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('salutationKey', SalutationDefinition::NOT_SPECIFIED));

        return $this->salutationRepository->searchIds($criteria, $context->getContext())->firstId();
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Contena\Core\Checkout\Customer\CustomerCollection;
use Contena\Core\Checkout\Customer\CustomerDefinition;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Checkout\Customer\CustomerEvents;
use Contena\Core\Checkout\Customer\Extension\ChangeCustomerProfileRouteExtension;
use Contena\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentification;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Event\DataMappingEvent;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Contena\Core\System\SalesChannel\SuccessResponse;
use Contena\Core\System\Salutation\SalutationCollection;
use Contena\Core\System\Salutation\SalutationDefinition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
#[Route(
    defaults: [
        PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID],
        PlatformRequest::ATTRIBUTE_CONTEXT_TOKEN_REQUIRED => true,
    ]
)]
class ChangeCustomerProfileRoute extends AbstractChangeCustomerProfileRoute
{
    use CustomerVatIdNormalizerTrait;

    /**
     * @internal
     *
     * @param EntityRepository<CustomerCollection> $customerRepository
     * @param EntityRepository<SalutationCollection> $salutationRepository
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $validator,
        private readonly DataValidationFactoryInterface $customerProfileValidationFactory,
        private readonly StoreApiCustomFieldMapper $storeApiCustomFieldMapper,
        private readonly EntityRepository $salutationRepository,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractChangeCustomerProfileRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/account/change-profile',
        name: 'store-api.account.change-profile',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED_ALLOW_GUEST => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function change(RequestDataBag $data, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse
    {
        return $this->extensions->publish(
            name: ChangeCustomerProfileRouteExtension::NAME,
            extension: new ChangeCustomerProfileRouteExtension($data, $context, $customer),
            function: $this->_change(...),
        );
    }

    private function _change(RequestDataBag $data, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse
    {
        $validation = $this->customerProfileValidationFactory->update($context);

        if ($data->has('accountType') && $data->getString('accountType') === '') {
            $data->remove('accountType');
        }

        $keepsAccountType = !$data->has('accountType');
        $accountType = $keepsAccountType ? $customer->getAccountType() : $data->get('accountType');

        if ($accountType === CustomerEntity::ACCOUNT_TYPE_BUSINESS) {
            if (!$keepsAccountType || $data->has('company')) {
                $validation->add('company', new NotBlank());
            }

            $billingAddress = $customer->getActiveBillingAddress();
            if ($billingAddress && (!$keepsAccountType || $data->has('vatIds'))) {
                $this->addVatIdsValidation($validation, $billingAddress);
            }
        } else {
            $data->set('company', '');
            $data->set('vatIds', null);
        }

        $vatIds = $data->get('vatIds');
        if ($vatIds instanceof RequestDataBag) {
            $vatIds = \array_filter($this->normalizeVatIds($vatIds->all()));
            $data->set('vatIds', $vatIds === [] ? null : $vatIds);
        }

        if (!$data->get('salutationId')) {
            $data->set('salutationId', $this->getDefaultSalutationId($context));
        }

        $this->dispatchValidationEvent($validation, $data, $context->getContext());

        $this->validator->validate($data->all(), $validation);

        $customerData = $data->only('firstName', 'lastName', 'salutationId', 'title', 'company', 'accountType');

        if (!$keepsAccountType || $data->has('vatIds')) {
            $vatIds = $data->get('vatIds');

            if ($vatIds instanceof DataBag) {
                $vatIds = $vatIds->all();
            }

            $customerData['vatIds'] = $vatIds;
        }

        if ($birthday = $this->getBirthday($data)) {
            $customerData['birthday'] = $birthday;
        }

        if ($data->get('customFields') instanceof RequestDataBag) {
            $customerData['customFields'] = $this->storeApiCustomFieldMapper->map(
                CustomerDefinition::ENTITY_NAME,
                $data->get('customFields')
            );
            if ($customerData['customFields'] === []) {
                unset($customerData['customFields']);
            }
        }

        $mappingEvent = new DataMappingEvent($data, $customerData, $context->getContext());
        $this->eventDispatcher->dispatch($mappingEvent, CustomerEvents::MAPPING_CUSTOMER_PROFILE_SAVE);

        $customerData = $mappingEvent->getOutput();

        $customerData['id'] = $customer->getId();

        $this->customerRepository->update([$customerData], $context->getContext());

        return new SuccessResponse();
    }

    private function dispatchValidationEvent(DataValidationDefinition $definition, DataBag $data, Context $context): void
    {
        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());
    }

    private function addVatIdsValidation(DataValidationDefinition $validation, CustomerAddressEntity $address): void
    {
        $constraints = [
            new Type('array'),
            new CustomerVatIdentification(
                countryId: $address->getCountryId()
            ),
        ];
        if ($address->getCountry() && $address->getCountry()->getVatIdRequired()) {
            $constraints[] = new NotBlank();
        }

        $validation->add('vatIds', ...$constraints);
    }

    private function getBirthday(DataBag $data): ?\DateTimeInterface
    {
        $birthdayDay = $data->get('birthdayDay');
        $birthdayMonth = $data->get('birthdayMonth');
        $birthdayYear = $data->get('birthdayYear');

        if (!\is_numeric($birthdayDay) || !\is_numeric($birthdayMonth) || !\is_numeric($birthdayYear)) {
            return null;
        }

        return new \DateTime(\sprintf(
            '%s-%s-%s',
            $birthdayYear,
            $birthdayMonth,
            $birthdayDay
        ));
    }

    private function getDefaultSalutationId(SalesChannelContext $context): ?string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new EqualsFilter('salutationKey', SalutationDefinition::NOT_SPECIFIED));

        return $this->salutationRepository->searchIds($criteria, $context->getContext())->firstId();
    }
}

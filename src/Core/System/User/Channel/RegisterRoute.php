<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\ChannelApiCustomFieldMapper;
use Contena\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\Service\DoubleOptInService;
use Contena\Core\System\User\UserCollection;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Contena\Core\Framework\Event\DataMappingEvent;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressDefinition;
use Contena\Core\System\User\Event\GuestUserRegisterEvent;
use Contena\Core\System\User\Event\UserLoginEvent;
use Contena\Core\System\User\Event\UserRegisterEvent;
use Contena\Core\System\User\Extension\RegisterRouteExtension;
use Contena\Core\System\User\Service\EmailIdnConverter;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserEvents;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Validation\Constraint\UserEmailUnique;
use Contena\Core\System\User\Validation\Constraint\UserVatIdentification;
use Contena\Core\System\User\Validation\Constraint\UserZipCode;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\AtLeastOneOf;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\IsNull;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\User\Channel\UserAddressDataNormalizerTrait;
use Contena\Core\System\User\Channel\UserVatIdNormalizerTrait;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class RegisterRoute extends AbstractRegisterRoute
{
    use UserAddressDataNormalizerTrait;
    use UserVatIdNormalizerTrait;

    /**
     * @internal
     *
     * @param EntityRepository<UserCollection> $userRepository
     * @param ChannelRepository<UserCollection> $countryRepository
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly NumberRangeValueGeneratorInterface $numberRangeValueGenerator,
        private readonly DataValidator $validator,
        private readonly DataValidationFactoryInterface $accountValidationFactory,
        private readonly DataValidationFactoryInterface $addressValidationFactory,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $userRepository,
        private readonly ChannelContextPersister $contextPersister,
        private readonly ChannelRepository $countryRepository,
        protected Connection $connection,
        private readonly ChannelContextServiceInterface $contextService,
        private readonly ChannelApiCustomFieldMapper $customFieldMapper,
        private readonly DataValidationFactoryInterface $passwordValidationFactory,
        private readonly DoubleOptInService $doubleOptInService,
        private readonly ClockInterface $clock,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractRegisterRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/channel-api/account/register', name: 'channel-api.account.register', methods: ['POST'])]
    public function register(
        RequestDataBag $data,
        ChannelContext $context,
        bool $validateFrontendUrl = true,
        ?DataValidationDefinition $additionalValidationDefinitions = null
    ): UserResponse {
        return $this->extensions->publish(
            name: RegisterRouteExtension::NAME,
            extension: new RegisterRouteExtension($data, $context, $validateFrontendUrl, $additionalValidationDefinitions),
            function: $this->_register(...),
        );
    }

    private function _register(
        RequestDataBag $data,
        ChannelContext $context,
        bool $validateFrontendUrl,
        ?DataValidationDefinition $additionalValidationDefinitions
    ): UserResponse {
        EmailIdnConverter::encodeDataBag($data);

        $isGuest = $data->getBoolean('guest');

        if ($data->has('accountType') && $data->getString('accountType') === '') {
            $data->remove('accountType');
        }


        $billing = $data->get('billingAddress');
        $shipping = $data->get('shippingAddress');

        if ($billing instanceof DataBag) {
            if ($billing->has('name') && !$data->has('name')) {
                $data->set('name', $billing->get('name'));
            }


            if ($data->has('title')) {
                $billing->set('title', $data->get('title'));
            }
        }

        $this->validateRegistrationData($data, $isGuest, $context, $additionalValidationDefinitions, $validateFrontendUrl);

        $user = $this->mapUserData($data, $isGuest, $context);

        if ($billing instanceof DataBag) {
            $billingAddress = $this->mapAddressData($billing, $context->getContext(), UserEvents::MAPPING_REGISTER_ADDRESS_BILLING);
            $billingAddress['id'] = Uuid::randomHex();
            $billingAddress['userId'] = $user['id'];
            $user['defaultBillingAddressId'] = $billingAddress['id'];
            $user['addresses'][] = $billingAddress;

            if (!$shipping) {
                $user['defaultShippingAddressId'] = $billingAddress['id'];
            }
        }

        if ($shipping instanceof DataBag) {
            $shippingAddress = $this->mapAddressData($shipping, $context->getContext(), UserEvents::MAPPING_REGISTER_ADDRESS_SHIPPING);
            $shippingAddress['id'] = Uuid::randomHex();
            $shippingAddress['userId'] = $user['id'];

            $user['defaultShippingAddressId'] = $shippingAddress['id'];
            $user['addresses'][] = $shippingAddress;

            if (!$billing) {
                $user['defaultBillingAddressId'] = $shippingAddress['id'];
            }
        }

        if ($data->get('accountType')) {
            $user['accountType'] = $data->get('accountType');
        }

        $companyName = $billingAddress['company'] ?? $shippingAddress['company'] ?? null;
        if ($data->get('accountType') === UserEntity::ACCOUNT_TYPE_BUSINESS && $companyName) {
            $user['company'] = $companyName;
            if ($data->get('vatIds')) {
                $user['vatIds'] = $data->get('vatIds');
            }
        }

        $user = $this->doubleOptInService->mapUserDoubleOptInData($user, $context);

        $user['boundChannelId'] = $this->getBoundChannelId($user['email'], $context);

        if ($data->get('customFields') instanceof RequestDataBag) {
            $user['customFields'] = $this->customFieldMapper->map(UserDefinition::ENTITY_NAME, $data->get('customFields'));
        }

        // Convert all DataBags to array
        $user = array_map(static function (mixed $value) {
            if ($value instanceof DataBag) {
                return $value->all();
            }

            return $value;
        }, $user);

        $writeContext = clone $context->getContext();
        $writeContext->addState(EntityIndexerRegistry::USE_INDEXING_QUEUE);

        $this->userRepository->create([$user], $writeContext);

        $criteria = new Criteria([$user['id']]);

        $userEntity = $this->userRepository->search($criteria, $context->getContext())->getEntities()->first();
        \assert(assertion: $userEntity !== null);

        if ($userEntity->getDoubleOptInRegistration()) {
            $this->doubleOptInService->sendDoubleOptInMail(
                $userEntity,
                $context,
                (string) $data->get('frontendUrl'),
                $data->get('redirectTo'),
                $data->get('redirectParameters')
            );

            // We don't want to leak the hash in channel-api
            $userEntity->setHash('');

            return new UserResponse($userEntity);
        }

        $response = new UserResponse($userEntity);

        $newToken = $this->contextPersister->replace($context->getToken(), $context);

        $this->contextPersister->save(
            $newToken,
            [
                'userId' => $userEntity->getId(),
                'billingAddressId' => null,
                'shippingAddressId' => null,
                'domainId' => $context->getDomainId(),
            ],
            $context->getChannelId(),
            $userEntity->getId()
        );

        $new = $this->contextService->get(
            new ChannelContextServiceParameters(
                $context->getChannelId(),
                $newToken,
                $context->getLanguageId(),
                $context->getCurrencyId(),
                $context->getDomainId(),
                null,
                $userEntity->getId()
            )
        );

        $new->addState(...$context->getStates());

        if (!$userEntity->getGuest()) {
            $this->eventDispatcher->dispatch(new UserRegisterEvent($new, $userEntity));
        } else {
            $this->eventDispatcher->dispatch(new GuestUserRegisterEvent($new, $userEntity));
        }

        $event = new UserLoginEvent($new, $userEntity, $newToken);
        $this->eventDispatcher->dispatch($event);

        $response->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $newToken);

        // We don't want to leak the hash in channel-api
        $userEntity->setHash('');

        return $response;
    }

    private function validateRegistrationData(
        DataBag $data,
        bool $isGuest,
        ChannelContext $context,
        ?DataValidationDefinition $additionalValidations,
        bool $validateFrontendUrl
    ): void {
        $billingAddress = $data->get('billingAddress');
        $shippingAddress = $data->get('shippingAddress');
        if ($billingAddress instanceof DataBag) {
            $billingAddress->set('name', $data->get('name'));
            $billingAddress->set($data->get('salutationId'));
        }

        if (($shippingAddress instanceof DataBag) && !$shippingAddress->get('salutationId')) {
            $shippingAddress->set($data->get('salutationId'));
        }

        $definition = $this->getUserCreateValidationDefinition($isGuest, $data, $context);

        if ($additionalValidations) {
            $definition->merge($additionalValidations);
        }

        if ($validateFrontendUrl && $this->doubleOptInService->isDoubleOptInEnabled($isGuest, $context)) {
            $definition
                ->add('frontendUrl', new NotBlank(), new Choice(choices: $this->getDomainUrls($context)));
        }

        $accountType = $data->get('accountType', UserEntity::ACCOUNT_TYPE_PRIVATE);

        // The billing address is mandatory.
        // The shipping address is optional but if there is one, a non-array value results in an exception in the validation process.
        $definition->add(
            'billingAddress',
            new Type('associative_array', message: 'VIOLATION::BILLING_ADDRESS_INVALID_TYPE_ERROR')
        );
        $definition->add(
            'shippingAddress',
            new AtLeastOneOf([
                new Type('associative_array', message: 'VIOLATION::SHIPPING_ADDRESS_INVALID_TYPE_ERROR'),
                new IsNull(),
            ])
        );

        // The billing address validation must not be added if the data is neither a data bag nor valid because the validation building will fail.
        // Using a null value must be possible to allow the event based modification (see BuildValidationEvent).
        if ($billingAddress instanceof DataBag || (!$shippingAddress instanceof DataBag && $billingAddress === null)) {
            $definition->addSub('billingAddress', $this->getCreateAddressValidationDefinition($data, $accountType, $billingAddress ?? new RequestDataBag(), $context));
        }

        if ($shippingAddress instanceof DataBag) {
            $shippingAccountType = $shippingAddress->get('accountType', UserEntity::ACCOUNT_TYPE_PRIVATE);
            $definition->addSub('shippingAddress', $this->getCreateAddressValidationDefinition($data, $shippingAccountType, $shippingAddress, $context));
        }

        if ($this->systemConfigService->get('core.loginRegistration.requireDataProtectionCheckbox', $context->getChannelId())) {
            $definition->add('acceptedDataProtection', new NotBlank());
        }

        $violations = $this->validator->getViolations($data->all(), $definition);

        if (!$violations->count()) {
            return;
        }

        throw new ConstraintViolationException($violations, $data->all());
    }

    /**
     * @return list<string>
     */
    private function getDomainUrls(ChannelContext $context): array
    {
        $channelDomainCollection = $context->getChannel()->getDomains();
        \assert($channelDomainCollection instanceof ChannelDomainCollection);

        return array_values(array_map(static fn (ChannelDomainEntity $domainEntity) => rtrim($domainEntity->getUrl(), '/'), $channelDomainCollection->getElements()));
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
            '%d-%d-%d',
            (int) $birthdayYear,
            (int) $birthdayMonth,
            (int) $birthdayDay
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapUserData(DataBag $data, bool $isGuest, ChannelContext $context): array
    {
        $user = [
            'userNumber' => $this->numberRangeValueGenerator->getValue(
                $this->userRepository->getDefinition()->getEntityName(),
                $context->getContext(),
                $context->getChannelId()
            ),
            'channelId' => $context->getChannelId(),
            'languageId' => $context->getLanguageId(),
            'groupId' => $context->getUserGroupId(),
            'requestedGroupId' => $data->get('requestedGroupId', null),
            'name' => $data->get('name'),
            'email' => $data->get('email'),
            'title' => $data->get('title'),
            'active' => true,
            'birthday' => $this->getBirthday($data),
            'guest' => $isGuest,
            'firstLogin' => $this->clock->now(),
            'addresses' => [],
        ];

        if (!$isGuest) {
            $user['password'] = $data->get('password');
        }

        $event = new DataMappingEvent($data, $user, $context->getContext());
        $this->eventDispatcher->dispatch($event, UserEvents::MAPPING_REGISTER_CUSTOMER);

        $user = $event->getOutput();
        $user['id'] = Uuid::randomHex();

        return $user;
    }

    private function getCreateAddressValidationDefinition(
        DataBag $data,
        ?string $accountType,
        DataBag $address,
        ChannelContext $context
    ): DataValidationDefinition {
        $validation = $this->addressValidationFactory->create($context);

        if ($accountType === UserEntity::ACCOUNT_TYPE_BUSINESS
            && $this->systemConfigService->get('core.loginRegistration.showAccountTypeSelection', $context->getChannelId())) {
            $validation->add('company', new NotBlank());
        }

        $validation->set('zipcode', new UserZipCode(countryId: $address->get('countryId')));
        $validation->add('zipcode', new Length(max: 50));

        $validationEvent = new BuildValidationEvent($validation, $data, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        return $validation;
    }

    private function getUserCreateValidationDefinition(bool $isGuest, DataBag $data, ChannelContext $context): DataValidationDefinition
    {
        $validation = $this->accountValidationFactory->create($context);

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('registrationChannels.id', $context->getChannelId()));

        $validation->add('requestedGroupId', new EntityExists(
            entity: 'user_group',
            context: $context->getContext(),
            criteria: $criteria,
        ));

        if (!$isGuest) {
            $validation->merge(
                $this->passwordValidationFactory->create($context)
            );
            $validation->add('email', new UserEmailUnique(channelContext: $context));
        }

        $this->addVatIdsValidation($validation, $data, $context);

        $validationEvent = new BuildValidationEvent($validation, $data, $context->getContext());
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        return $validation;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapAddressData(DataBag $addressData, Context $context, string $eventName): array
    {
        $mappedData = $addressData->only(
            'title',
            'name',
            'salutationId',
            'street',
            'zipcode',
            'city',
            'company',
            'department',
            'countryStateId',
            'countryId',
            'additionalAddressLine1',
            'additionalAddressLine2',
            'phoneNumber'
        );

        if (isset($mappedData['countryStateId']) && $mappedData['countryStateId'] === '') {
            $mappedData['countryStateId'] = null;
        }

        $mappedData = $this->trimAddressFields($mappedData);

        if ($addressData->get('customFields') instanceof RequestDataBag) {
            $mappedData['customFields'] = $this->customFieldMapper->map(UserAddressDefinition::ENTITY_NAME, $addressData->get('customFields'));
        }

        $event = new DataMappingEvent($addressData, $mappedData, $context);
        $this->eventDispatcher->dispatch($event, $eventName);

        return $event->getOutput();
    }

    private function getBoundChannelId(string $email, ChannelContext $context): ?string
    {
        $bindUsers = $this->systemConfigService->get('core.systemWideLoginRegistration.isUserBoundToChannel');
        $channelId = $context->getChannelId();

        if ($bindUsers) {
            return $channelId;
        }

        if ($this->hasBoundAccount($email)) {
            return $channelId;
        }

        return null;
    }

    private function hasBoundAccount(string $email): bool
    {
        $query = $this->connection->createQueryBuilder();

        $results = $query
            ->select('LOWER(HEX(bound_channel_id)) as bound_channel_id')
            ->from('user')
            ->where($query->expr()->eq('email', $query->createPositionalParameter($email)))
            ->executeQuery()
            ->fetchAllAssociative();

        foreach ($results as $result) {
            if ($result['bound_channel_id']) {
                return true;
            }
        }

        return false;
    }

    /**
     * The VAT ID constraints are added before the BuildValidationEvent is dispatched, so that subscribers can modify them.
     */
    private function addVatIdsValidation(DataValidationDefinition $validation, DataBag $data, ChannelContext $context): void
    {
        if ($data->get('vatIds') instanceof DataBag) {
            $vatIds = array_filter($data->get('vatIds')->all());
            $data->set('vatIds', $vatIds);
        }

        if ($data->get('accountType', UserEntity::ACCOUNT_TYPE_PRIVATE) !== UserEntity::ACCOUNT_TYPE_BUSINESS) {
            return;
        }

        $billingAddress = $data->get('billingAddress');
        $shippingAddress = $data->get('shippingAddress');

        $countryId = $billingAddress instanceof DataBag
            ? $billingAddress->get('countryId')
            : ($shippingAddress instanceof DataBag ? $shippingAddress->get('countryId') : null);

        if (!$countryId) {
            return;
        }

        if ($this->requiredVatIdField($countryId, $context)) {
            $validation->add('vatIds', new NotBlank());
        }

        $validation->add('vatIds', new Type('array'), new UserVatIdentification(
            countryId: $countryId
        ));

        $vatIds = $data->get('vatIds');
        if ($vatIds instanceof DataBag) {
            $vatIds = $vatIds->all();
        }

        if (\is_array($vatIds) && $vatIds !== []) {
            $data->set('vatIds', $this->normalizeVatIds($vatIds));
        }
    }

    private function requiredVatIdField(string $countryId, ChannelContext $context): bool
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $country = $this->countryRepository->search(new Criteria([$countryId]), $context)->getEntities()->get($countryId);

            if (!$country) {
                throw UserException::countryNotFound($countryId);
            }

            return $country->getVatIdRequired();
        }

        $countryCriteria = (new Criteria([$countryId]))
            ->addFields(['vatIdRequired']);

        $country = $this->countryRepository->search($countryCriteria, $context)->getEntities()->first();
        if (!$country) {
            throw UserException::countryNotFound($countryId);
        }

        return $country->get('vatIdRequired');
    }

}

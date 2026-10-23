<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Event\DataMappingEvent;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\StoreApiCustomFieldMapper;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\Extension\ChangeUserProfileRouteExtension;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserEvents;
use Contena\Core\System\User\Validation\Constraint\UserVatIdentification;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\User\Channel\UserVatIdNormalizerTrait;

#[Route(
    defaults: [
        PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID],
        PlatformRequest::ATTRIBUTE_CONTEXT_TOKEN_REQUIRED => true,
    ]
)]
class ChangeUserProfileRoute extends AbstractChangeUserProfileRoute
{
    use UserVatIdNormalizerTrait;

    /**
     * @internal
     *
     * @param EntityRepository<UserCollection> $userRepository
     */
    public function __construct(
        private readonly EntityRepository $userRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $validator,
        private readonly DataValidationFactoryInterface $userProfileValidationFactory,
        private readonly StoreApiCustomFieldMapper $storeApiCustomFieldMapper,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractChangeUserProfileRoute
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
    public function change(RequestDataBag $data, ChannelContext $context, UserEntity $user): SuccessResponse
    {
        return $this->extensions->publish(
            name: ChangeUserProfileRouteExtension::NAME,
            extension: new ChangeUserProfileRouteExtension($data, $context, $user),
            function: $this->_change(...),
        );
    }

    private function _change(RequestDataBag $data, ChannelContext $context, UserEntity $user): SuccessResponse
    {
        $validation = $this->userProfileValidationFactory->update($context);

        if ($data->has('accountType') && $data->getString('accountType') === '') {
            $data->remove('accountType');
        }

        $keepsAccountType = !$data->has('accountType');
        $accountType = $keepsAccountType ? $user->getAccountType() : $data->get('accountType');

        if ($accountType === UserEntity::ACCOUNT_TYPE_BUSINESS) {
            if (!$keepsAccountType || $data->has('company')) {
                $validation->add('company', new NotBlank());
            }

            $billingAddress = $user->getActiveBillingAddress();
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


        $this->dispatchValidationEvent($validation, $data, $context->getContext());

        $this->validator->validate($data->all(), $validation);

        $userData = $data->only('firstName', 'lastName', 'title', 'company', 'accountType');

        if (!$keepsAccountType || $data->has('vatIds')) {
            $vatIds = $data->get('vatIds');

            if ($vatIds instanceof DataBag) {
                $vatIds = $vatIds->all();
            }

            $userData['vatIds'] = $vatIds;
        }

        if ($birthday = $this->getBirthday($data)) {
            $userData['birthday'] = $birthday;
        }

        if ($data->get('customFields') instanceof RequestDataBag) {
            $userData['customFields'] = $this->storeApiCustomFieldMapper->map(
                UserDefinition::ENTITY_NAME,
                $data->get('customFields')
            );
            if ($userData['customFields'] === []) {
                unset($userData['customFields']);
            }
        }

        $mappingEvent = new DataMappingEvent($data, $userData, $context->getContext());
        $this->eventDispatcher->dispatch($mappingEvent, UserEvents::MAPPING_CUSTOMER_PROFILE_SAVE);

        $userData = $mappingEvent->getOutput();

        $userData['id'] = $user->getId();

        $this->userRepository->update([$userData], $context->getContext());

        return new SuccessResponse();
    }

    private function dispatchValidationEvent(DataValidationDefinition $definition, DataBag $data, Context $context): void
    {
        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());
    }

    private function addVatIdsValidation(DataValidationDefinition $validation, UserAddressEntity $address): void
    {
        $constraints = [
            new Type('array'),
            new UserVatIdentification(
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

}

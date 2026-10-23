<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryCollection;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Extension\ChangeEmailRouteExtension;
use Contena\Core\System\User\Service\EmailIdnConverter;
use Contena\Core\System\User\Validation\Constraint\UserEmailUnique;
use Contena\Core\System\User\Validation\Constraint\UserPasswordMatches;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(
    defaults: [
        PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID],
        PlatformRequest::ATTRIBUTE_CONTEXT_TOKEN_REQUIRED => true,
    ]
)]
class ChangeEmailRoute extends AbstractChangeEmailRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<UserCollection> $userRepository
     * @param EntityRepository<UserRecoveryCollection> $userRecoveryRepository
     */
    public function __construct(
        private readonly EntityRepository $userRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $validator,
        private readonly EntityRepository $userRecoveryRepository,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractChangeEmailRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/account/change-email',
        name: 'store-api.account.change-email',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function change(RequestDataBag $requestDataBag, ChannelContext $context, UserEntity $user): SuccessResponse
    {
        return $this->extensions->publish(
            name: ChangeEmailRouteExtension::NAME,
            extension: new ChangeEmailRouteExtension($requestDataBag, $context, $user),
            function: $this->_change(...),
        );
    }

    private function _change(RequestDataBag $requestDataBag, ChannelContext $context, UserEntity $user): SuccessResponse
    {
        EmailIdnConverter::encodeDataBag($requestDataBag);
        EmailIdnConverter::encodeDataBag($requestDataBag, 'emailConfirmation');

        $this->validateEmail($requestDataBag, $context);

        $userData = [
            'id' => $user->getId(),
            'email' => $requestDataBag->get('email'),
        ];

        $this->userRepository->update([$userData], $context->getContext());

        $criteria = (new Criteria())->addFilter(new EqualsFilter('userId', $user->getId()));
        $ids = $this->userRecoveryRepository->searchIds($criteria, $context->getContext())->getPrimaryKeyData();
        if ($ids !== []) {
            $this->userRecoveryRepository->delete($ids, $context->getContext());
        }

        return new SuccessResponse();
    }

    private function validateEmail(DataBag $data, ChannelContext $context): void
    {
        $validation = new DataValidationDefinition('user.email.update');

        $validation
            ->add(
                'email',
                new Email(),
                new EqualTo(propertyPath: 'emailConfirmation'),
                new UserEmailUnique(channelContext: $context)
            )
            ->add('password', new UserPasswordMatches(channelContext: $context));

        $this->dispatchValidationEvent($validation, $data, $context->getContext());

        $this->validator->validate($data->all(), $validation);

        $this->tryValidateEqualToConstraint($data->all(), 'email', $validation);
    }

    private function dispatchValidationEvent(DataValidationDefinition $definition, DataBag $data, Context $context): void
    {
        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function tryValidateEqualToConstraint(array $data, string $field, DataValidationDefinition $validation): void
    {
        $validations = $validation->getProperties();

        $fieldValidations = $validations[$field] ?? [];

        $equalityValidation = null;

        foreach ($fieldValidations as $emailValidation) {
            if ($emailValidation instanceof EqualTo) {
                $equalityValidation = $emailValidation;

                break;
            }
        }

        if (!$equalityValidation instanceof EqualTo) {
            return;
        }

        $compareValue = $data[$equalityValidation->propertyPath ?? ''] ?? null;
        if ($data[$field] === $compareValue) {
            return;
        }

        $message = str_replace('{{ compared_value }}', $compareValue, $equalityValidation->message);

        $violations = new ConstraintViolationList();
        $violations->add(new ConstraintViolation($message, $equalityValidation->message, [], '', $field, $data[$field]));

        throw new ConstraintViolationException($violations, $data);
    }
}

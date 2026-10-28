<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryCollection;
use Contena\Core\Checkout\Customer\CustomerCollection;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Checkout\Customer\Extension\ChangeEmailRouteExtension;
use Contena\Core\Checkout\Customer\Service\EmailIdnConverter;
use Contena\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique;
use Contena\Core\Checkout\Customer\Validation\Constraint\CustomerPasswordMatches;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\SalesChannel\SuccessResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
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
     * @param EntityRepository<CustomerCollection> $customerRepository
     * @param EntityRepository<CustomerRecoveryCollection> $customerRecoveryRepository
     */
    public function __construct(
        private readonly EntityRepository $customerRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $validator,
        private readonly EntityRepository $customerRecoveryRepository,
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
    public function change(RequestDataBag $requestDataBag, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse
    {
        return $this->extensions->publish(
            name: ChangeEmailRouteExtension::NAME,
            extension: new ChangeEmailRouteExtension($requestDataBag, $context, $customer),
            function: $this->_change(...),
        );
    }

    private function _change(RequestDataBag $requestDataBag, SalesChannelContext $context, CustomerEntity $customer): SuccessResponse
    {
        EmailIdnConverter::encodeDataBag($requestDataBag);
        EmailIdnConverter::encodeDataBag($requestDataBag, 'emailConfirmation');

        $this->validateEmail($requestDataBag, $context);

        $customerData = [
            'id' => $customer->getId(),
            'email' => $requestDataBag->get('email'),
        ];

        $this->customerRepository->update([$customerData], $context->getContext());

        $criteria = (new Criteria())->addFilter(new EqualsFilter('customerId', $customer->getId()));
        $ids = $this->customerRecoveryRepository->searchIds($criteria, $context->getContext())->getPrimaryKeyData();
        if ($ids !== []) {
            $this->customerRecoveryRepository->delete($ids, $context->getContext());
        }

        return new SuccessResponse();
    }

    private function validateEmail(DataBag $data, SalesChannelContext $context): void
    {
        $validation = new DataValidationDefinition('customer.email.update');

        $validation
            ->add(
                'email',
                new Email(),
                new EqualTo(propertyPath: 'emailConfirmation'),
                new CustomerEmailUnique(salesChannelContext: $context)
            )
            ->add('password', new CustomerPasswordMatches(salesChannelContext: $context));

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

<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Checkout\Customer\CustomerException;
use Contena\Core\Checkout\Customer\Extension\ImitateCustomerRouteExtension;
use Contena\Core\Checkout\Customer\ImitateCustomerTokenGenerator;
use Contena\Core\Checkout\Customer\Struct\ImitateCustomerToken;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Contena\Core\Framework\Deprecation\BCChange\ParameterNameChange;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\Constraint\Uuid;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Contena\Core\System\SalesChannel\ContextTokenResponse;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
#[Route(
    defaults: [
        PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID],
        PlatformRequest::ATTRIBUTE_CONTEXT_TOKEN_REQUIRED => false,
    ]
)]
class ImitateCustomerRoute extends AbstractImitateCustomerRoute
{
    final public const TOKEN = 'token';

    /**
     * @deprecated tag:v6.8.0 - will be removed, will be sourced from JWT
     */
    final public const CUSTOMER_ID = 'customerId';

    /**
     * @deprecated tag:v6.8.0 - will be removed, will be sourced from JWT
     */
    final public const USER_ID = 'userId';

    /**
     * @internal
     */
    public function __construct(
        private readonly AccountService $accountService,
        private readonly ImitateCustomerTokenGenerator $imitateCustomerTokenGenerator,
        private readonly AbstractLogoutRoute $logoutRoute,
        private readonly AbstractSalesChannelContextFactory $salesChannelContextFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $validator,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractImitateCustomerRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[ParameterNameChange(version: 'v6.8.0', parameterName: 'requestDataBag', newName: 'data', description: 'Aligns with the abstract route.')]
    #[Route(
        path: '/store-api/account/login/imitate-customer',
        name: 'store-api.account.imitate-customer-login',
        methods: [Request::METHOD_POST]
    )]
    public function imitateCustomerLogin(RequestDataBag $requestDataBag, SalesChannelContext $context): ContextTokenResponse
    {
        return $this->extensions->publish(
            name: ImitateCustomerRouteExtension::NAME,
            extension: new ImitateCustomerRouteExtension($requestDataBag, $context),
            function: $this->_imitateCustomerLogin(...),
        );
    }

    private function _imitateCustomerLogin(RequestDataBag $data, SalesChannelContext $context): ContextTokenResponse
    {
        $tokenString = $data->getString(self::TOKEN);

        if (!Feature::isActive('v6.8.0.0')) {
            $this->validateRequestDataFields($data, $context->getContext());

            $token = new ImitateCustomerToken();
            $token->customerId = $data->getString(self::CUSTOMER_ID);
            $token->iss = $data->getString(self::USER_ID);

            Feature::silent('v6.8.0.0', fn () => $this->imitateCustomerTokenGenerator->validate($tokenString, $context->getSalesChannelId(), $token->customerId, $token->iss));
        } else {
            $token = $this->imitateCustomerTokenGenerator->decode($tokenString);

            if ($token->salesChannelId !== $context->getSalesChannelId()) {
                throw CustomerException::invalidImitationToken($tokenString);
            }
        }

        if ($context->getCustomerId() === $token->customerId) {
            return new ContextTokenResponse($context->getToken());
        }

        if ($context->getCustomer()) {
            $newTokenResponse = $this->logoutRoute->logout($context, new RequestDataBag());

            $context = $this->salesChannelContextFactory->create($newTokenResponse->getToken(), $context->getSalesChannelId());
        }

        $context->setImitatingUserId($token->iss);

        $newToken = $this->accountService->loginById($token->customerId, $context);

        return new ContextTokenResponse($newToken);
    }

    /**
     * @throws ConstraintViolationException
     */
    private function validateRequestDataFields(DataBag $data, Context $context): void
    {
        $definition = new DataValidationDefinition('impersonation.login');

        $definition
            ->add(self::TOKEN, new NotBlank())
            ->add(self::CUSTOMER_ID, new Uuid(), new EntityExists(entity: 'customer', context: $context))
            ->add(self::USER_ID, new Uuid(), new EntityExists(entity: 'user', context: $context));

        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        $this->validator->validate($data->all(), $definition);
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Extension\ImitateUserRouteExtension;
use Contena\Core\System\User\ImitateUserTokenGenerator;
use Contena\Core\System\User\Struct\ImitateUserToken;
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
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(
    defaults: [
        PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID],
        PlatformRequest::ATTRIBUTE_CONTEXT_TOKEN_REQUIRED => false,
    ]
)]
class ImitateUserRoute extends AbstractImitateUserRoute
{
    final public const TOKEN = 'token';

    /**
     * @deprecated tag:v6.8.0 - will be removed, will be sourced from JWT
     */
    final public const CUSTOMER_ID = 'userId';

    /**
     * @deprecated tag:v6.8.0 - will be removed, will be sourced from JWT
     */
    final public const USER_ID = 'admin_userId';

    /**
     * @internal
     */
    public function __construct(
        private readonly AccountService $accountService,
        private readonly ImitateUserTokenGenerator $imitateUserTokenGenerator,
        private readonly AbstractLogoutRoute $logoutRoute,
        private readonly AbstractChannelContextFactory $channelContextFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly DataValidator $validator,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractImitateUserRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[ParameterNameChange(version: 'v6.8.0', parameterName: 'requestDataBag', newName: 'data', description: 'Aligns with the abstract route.')]
    #[Route(
        path: '/store-api/account/login/imitate-user',
        name: 'store-api.account.imitate-user-login',
        methods: [Request::METHOD_POST]
    )]
    public function imitateUserLogin(RequestDataBag $requestDataBag, ChannelContext $context): ContextTokenResponse
    {
        return $this->extensions->publish(
            name: ImitateUserRouteExtension::NAME,
            extension: new ImitateUserRouteExtension($requestDataBag, $context),
            function: $this->_imitateUserLogin(...),
        );
    }

    private function _imitateUserLogin(RequestDataBag $data, ChannelContext $context): ContextTokenResponse
    {
        $tokenString = $data->getString(self::TOKEN);

        if (!Feature::isActive('v6.8.0.0')) {
            $this->validateRequestDataFields($data, $context->getContext());

            $token = new ImitateUserToken();
            $token->userId = $data->getString(self::CUSTOMER_ID);
            $token->iss = $data->getString(self::USER_ID);

            Feature::silent('v6.8.0.0', fn () => $this->imitateUserTokenGenerator->validate($tokenString, $context->getChannelId(), $token->userId, $token->iss));
        } else {
            $token = $this->imitateUserTokenGenerator->decode($tokenString);

            if ($token->channelId !== $context->getChannelId()) {
                throw UserException::invalidImitationToken($tokenString);
            }
        }

        if ($context->getUserId() === $token->userId) {
            return new ContextTokenResponse($context->getToken());
        }

        if ($context->getUser()) {
            $newTokenResponse = $this->logoutRoute->logout($context, new RequestDataBag());

            $context = $this->channelContextFactory->create($newTokenResponse->getToken(), $context->getChannelId());
        }

        $context->setImitatingUserId($token->iss);

        $newToken = $this->accountService->loginById($token->userId, $context);

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
            ->add(self::CUSTOMER_ID, new Uuid(), new EntityExists(entity: 'user', context: $context))
            ->add(self::USER_ID, new Uuid(), new EntityExists(entity: 'admin_user', context: $context));

        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        $this->validator->validate($data->all(), $definition);
    }
}

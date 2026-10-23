<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Controller;

use Contena\Core\Checkout\Cart\ApiOrderCartService;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Processor;
use Contena\Core\Checkout\Cart\Channel\AbstractCartOrderRoute;
use Contena\Core\Checkout\Cart\Channel\CartService;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\CheckoutPermissions;
use Contena\Core\System\User\ImitateUserTokenGenerator;
use Contena\Core\System\User\Struct\ImitateUserToken;
use Contena\Core\Content\Flow\Dispatching\Action\SendMailAction;
use Contena\Core\Content\MailTemplate\Subscriber\MailSendSubscriberConfig;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Exception\InvalidChannelIdException;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Framework\Util\Random;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\Constraint\Uuid;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\PlatformRequest;
use Contena\Core\ChannelRequest;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextServiceParameters;
use Contena\Core\System\Channel\Event\ChannelContextSwitchEvent;
use Contena\Core\System\Channel\ChannelCollection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class ChannelProxyController extends AbstractController
{
    private const CUSTOMER_ID = ChannelContextService::CUSTOMER_ID;

    private const CHANNEL_ID = 'channelId';

    private const SEARCH_ROUTE = 'search';

    private const ADMIN_ORDER_PERMISSIONS = [
        CheckoutPermissions::ALLOW_BLOG_PRICE_OVERWRITES => true,
    ];

    protected Processor $processor;

    /**
     * @internal
     *
     * @param EntityRepository<ChannelCollection> $channelRepository
     */
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly EntityRepository $channelRepository,
        protected DataValidator $validator,
        protected ChannelContextPersister $contextPersister,
        private readonly ChannelContextServiceInterface $contextService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ApiOrderCartService $adminOrderCartService,
        private readonly AbstractCartOrderRoute $orderRoute,
        private readonly CartService $cartService,
        private readonly RequestStack $requestStack,
        private readonly ImitateUserTokenGenerator $imitateUserTokenGenerator
    ) {
    }

    #[Route(
        path: '/api/_proxy/store-api/{channelId}/{_path}',
        name: 'api.proxy.store-api',
        requirements: ['_path' => '.*']
    )]
    public function proxy(string $_path, string $channelId, Request $request, Context $context): Response
    {
        $channel = $this->fetchChannel($channelId, $context);

        $channelApiRequest = $this->setUpChannelApiRequest($_path, $channelId, $request, $channel, $context);

        return $this->wrapInChannelApiRoute($channelApiRequest, fn (): Response => $this->kernel->handle($channelApiRequest, HttpKernelInterface::SUB_REQUEST));
    }

    #[Route(
        path: '/api/_proxy-order/{channelId}',
        name: 'api.proxy-order.create'
    )]
    public function proxyCreateOrder(string $channelId, Request $request, Context $context, RequestDataBag $data): Response
    {
        $this->fetchChannel($channelId, $context);

        $channelContext = $this->fetchChannelContext($channelId, $request, $context);

        if ($request->request->get('sendOrderConfirmationMail', true) === false) {
            $channelContext->getContext()->addExtension(
                SendMailAction::MAIL_CONFIG_EXTENSION,
                new MailSendSubscriberConfig(true)
            );
        }

        $cart = $this->cartService->getCart($channelContext->getToken(), $channelContext);

        $order = $this->orderRoute->order($cart, $channelContext, $data)->getOrder();

        return new JsonResponse($order);
    }

    #[Route(
        path: '/api/_proxy/switch-user',
        name: 'api.proxy.switch-user',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['api_proxy_switch-user']],
        methods: [Request::METHOD_PATCH]
    )]
    public function assignUser(Request $request, Context $context): Response
    {
        if (!$request->request->has(self::CHANNEL_ID)) {
            throw ApiException::channelIdParameterIsMissing();
        }

        $channelId = (string) $request->request->get('channelId');

        if (!$request->request->has(self::CUSTOMER_ID)) {
            throw ApiException::channelIdParameterIsMissing();
        }

        $this->fetchChannel($channelId, $context);

        $channelContext = $this->fetchChannelContext($channelId, $request, $context);

        $this->persistPermissions($request, $channelContext);

        $this->updateUserToContext(RequestParamHelper::get($request, self::CUSTOMER_ID), $channelContext);

        $content = json_encode([
            PlatformRequest::HEADER_CONTEXT_TOKEN => $channelContext->getToken(),
        ], \JSON_THROW_ON_ERROR);
        $response = new Response();
        $response->headers->set('content-type', 'application/json');
        $response->setContent($content ?: null);

        return $response;
    }

    #[Route(
        path: '/api/_proxy/generate-imitate-user-token',
        name: 'api.proxy.generate-imitate-user-token',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['api_proxy_imitate-user']],
        methods: [Request::METHOD_POST]
    )]
    public function generateImitateUserToken(RequestDataBag $data, Context $context): JsonResponse
    {
        $this->validateImitateUserDataFields($data, $context);

        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw ApiException::invalidAdminSource($source::class);
        }

        $admin_userId = $source->getUserId();
        if (!$admin_userId) {
            throw ApiException::admin_userNotLoggedIn();
        }

        $channelId = $data->getString(self::CHANNEL_ID);
        $userId = $data->getString(self::CUSTOMER_ID);

        if (Feature::isActive('v6.8.0.0')) {
            $token = new ImitateUserToken();
            $token->channelId = $channelId;
            $token->userId = $userId;
            $token->iss = $admin_userId;

            $token = $this->imitateUserTokenGenerator->encode($token);
        } else {
            $token = Feature::silent('v6.8.0.0', fn () => $this->imitateUserTokenGenerator->generate($channelId, $userId, $admin_userId));
        }

        return new JsonResponse([
            'token' => $token,
        ]);
    }

    #[Route(
        path: '/api/_proxy/modify-shipping-costs',
        name: 'api.proxy.modify-shipping-costs',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['order:update']],
        methods: [Request::METHOD_PATCH]
    )]
    public function modifyShippingCosts(Request $request, Context $context): JsonResponse
    {
        if (!$request->request->has(self::CHANNEL_ID)) {
            throw ApiException::channelIdParameterIsMissing();
        }

        $channelId = (string) $request->request->get('channelId');

        $this->fetchChannel($channelId, $context);

        $channelContext = $this->fetchChannelContext($channelId, $request, $context);

        $calculatedPrice = $this->parseCalculatedPriceByRequest($request);

        $cart = $this->adminOrderCartService->updateShippingCosts($calculatedPrice, $channelContext);

        return new JsonResponse(['data' => $cart]);
    }

    #[Route(
        path: '/api/_proxy/disable-automatic-promotions',
        name: 'api.proxy.disable-automatic-promotions',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['order:update']],
        methods: [Request::METHOD_PATCH]
    )]
    public function disableAutomaticPromotions(Request $request): JsonResponse
    {
        if (!$request->request->has(self::CHANNEL_ID)) {
            throw ApiException::channelIdParameterIsMissing();
        }

        $contextToken = $this->getContextToken($request);

        $channelId = (string) $request->request->get('channelId');

        $this->adminOrderCartService->addPermission($contextToken, CheckoutPermissions::SKIP_AUTOMATIC_PROMOTIONS, $channelId);

        return new JsonResponse();
    }

    #[Route(
        path: '/api/_proxy/enable-automatic-promotions',
        name: 'api.proxy.enable-automatic-promotions',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['order:update']],
        methods: [Request::METHOD_PATCH]
    )]
    public function enableAutomaticPromotions(Request $request): JsonResponse
    {
        if (!$request->request->has(self::CHANNEL_ID)) {
            throw ApiException::channelIdParameterIsMissing();
        }

        $contextToken = $this->getContextToken($request);

        $channelId = (string) $request->request->get('channelId');

        $this->adminOrderCartService->deletePermission($contextToken, CheckoutPermissions::SKIP_AUTOMATIC_PROMOTIONS, $channelId);

        return new JsonResponse();
    }

    /**
     * @param callable(): Response $call
     */
    private function wrapInChannelApiRoute(Request $request, callable $call): Response
    {
        $requestStackBackup = $this->clearRequestStackWithBackup($this->requestStack);
        $this->requestStack->push($request);

        try {
            return $call();
        } finally {
            $this->restoreRequestStack($this->requestStack, $requestStackBackup);
        }
    }

    private function setUpChannelApiRequest(
        string $path,
        string $channelId,
        Request $request,
        ChannelEntity $channel,
        Context $context
    ): Request {
        $contextToken = $this->getContextToken($request);

        $server = array_merge($request->server->all(), ['REQUEST_URI' => '/store-api/' . $path]);
        $subrequest = $request->duplicate(null, null, [], null, null, $server);

        $subrequest->headers->set(PlatformRequest::HEADER_ACCESS_KEY, $channel->getAccessKey());
        $subrequest->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $contextToken);
        $subrequest->attributes->set(PlatformRequest::ATTRIBUTE_OAUTH_CLIENT_ID, $channel->getAccessKey());

        $channelContext = $this->fetchChannelContext($channelId, $subrequest, $context);

        if ($path === self::SEARCH_ROUTE) {
            $channelContext->getContext()->addState(Context::ELASTICSEARCH_EXPLAIN_MODE);
        }

        $subrequest->attributes->set(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT, $channelContext);
        $subrequest->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $channelContext->getContext());

        return $subrequest;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws InvalidChannelIdException
     */
    private function fetchChannel(string $channelId, Context $context): ChannelEntity
    {
        $channel = $this->channelRepository->search(new Criteria([$channelId]), $context)->getEntities()->get($channelId);

        if ($channel === null) {
            throw ApiException::invalidChannelId($channelId);
        }

        return $channel;
    }

    /**
     * @throws ConstraintViolationException
     */
    private function validateImitateUserDataFields(DataBag $data, Context $context): void
    {
        $definition = new DataValidationDefinition('impersonation.generate-token');

        $definition
            ->add(self::CHANNEL_ID, new Uuid(), new EntityExists(entity: 'channel', context: $context))
            ->add(self::CUSTOMER_ID, new Uuid(), new EntityExists(entity: 'user', context: $context));

        $validationEvent = new BuildValidationEvent($definition, $data, $context);
        $this->eventDispatcher->dispatch($validationEvent, $validationEvent->getName());

        $this->validator->validate($data->all(), $definition);
    }

    private function getContextToken(Request $request): string
    {
        $contextToken = $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        if ($contextToken === null) {
            $contextToken = Random::getAlphanumericString(32);
        }

        return $contextToken;
    }

    /**
     * @return array<Request>
     */
    private function clearRequestStackWithBackup(RequestStack $requestStack): array
    {
        $requestStackBackup = [];

        while ($requestStack->getMainRequest()) {
            $request = $requestStack->pop();

            if ($request === null) {
                continue;
            }

            $requestStackBackup[] = $request;
        }

        return $requestStackBackup;
    }

    /**
     * @param array<Request> $requestStackBackup
     */
    private function restoreRequestStack(RequestStack $requestStack, array $requestStackBackup): void
    {
        $this->clearRequestStackWithBackup($requestStack);

        foreach ($requestStackBackup as $backedUpRequest) {
            $requestStack->push($backedUpRequest);
        }
    }

    private function fetchChannelContext(
        string $channelId,
        Request $request,
        Context $originalContext
    ): ChannelContext {
        $contextToken = $this->getContextToken($request);

        return $this->contextService->get(
            new ChannelContextServiceParameters(
                $channelId,
                $contextToken,
                $request->headers->get(PlatformRequest::HEADER_LANGUAGE_ID),
                $request->attributes->get(ChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID),
                null,
                $originalContext
            )
        );
    }

    private function updateUserToContext(string $userId, ChannelContext $context): void
    {
        $data = new DataBag();
        $data->set(self::CUSTOMER_ID, $userId);

        $definition = new DataValidationDefinition('context_switch');
        $parameters = $data->only(
            self::CUSTOMER_ID
        );

        $userCriteria = new Criteria();
        $userCriteria->addFilter(new EqualsFilter('user.id', $parameters[self::CUSTOMER_ID]));

        $definition
            ->add(self::CUSTOMER_ID, new EntityExists(entity: 'user', context: $context->getContext(), criteria: $userCriteria))
        ;

        $this->validator->validate($parameters, $definition);

        $isSwitchNewUser = true;
        if ($context->getUser()) {
            // Check if user switch to another user or not
            $isSwitchNewUser = $context->getUserId() !== $parameters[self::CUSTOMER_ID];
        }

        if (!$isSwitchNewUser) {
            return;
        }

        $this->contextPersister->save(
            $context->getToken(),
            [
                'userId' => $parameters[self::CUSTOMER_ID],
                'billingAddressId' => null,
                'shippingAddressId' => null,
                'shippingMethodId' => null,
                'paymentMethodId' => null,
                'languageId' => null,
                'currencyId' => null,
            ],
            $context->getChannelId()
        );
        $event = new ChannelContextSwitchEvent($context, $data);
        $this->eventDispatcher->dispatch($event);
    }

    private function persistPermissions(Request $request, ChannelContext $channelContext): void
    {
        $contextToken = $channelContext->getToken();

        $channelId = $channelContext->getChannelId();

        $payload = $this->contextPersister->load($contextToken, $channelId);
        /** @var array<mixed>|null $requestPermissions */
        $requestPermissions = $request->request->all()[ChannelContextService::PERMISSIONS] ?? null;

        if (\in_array(ChannelContextService::PERMISSIONS, $payload, true) && !$requestPermissions) {
            return;
        }

        $payload[ChannelContextService::PERMISSIONS] = $requestPermissions
            ? \array_fill_keys($requestPermissions, true)
            : self::ADMIN_ORDER_PERMISSIONS;

        $this->contextPersister->save($contextToken, $payload, $channelId);
    }

    private function parseCalculatedPriceByRequest(Request $request): CalculatedPrice
    {
        $shippingCosts = $this->validateShippingCostsParameters($request);

        return new CalculatedPrice($shippingCosts['unitPrice'], $shippingCosts['totalPrice'], new CalculatedTaxCollection(), new TaxRuleCollection());
    }

    /**
     * @return array{unitPrice: float, totalPrice: float}
     */
    private function validateShippingCostsParameters(Request $request): array
    {
        if (!$request->request->has('shippingCosts')) {
            throw ApiException::shippingCostsParameterIsMissing();
        }

        $validation = new DataValidationDefinition('shipping-cost');
        $validation->add('unitPrice', new NotBlank(), new Type('numeric'), new GreaterThanOrEqual(value: 0));
        $validation->add('totalPrice', new NotBlank(), new Type('numeric'), new GreaterThanOrEqual(value: 0));
        $this->validator->validate($request->request->all('shippingCosts'), $validation);

        /** @var array{unitPrice: float, totalPrice: float} $shippingCosts otherwise validator would have thrown */
        $shippingCosts = $request->request->all('shippingCosts');

        return $shippingCosts;
    }
}

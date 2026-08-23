<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\QueryDataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\Channel\AbstractRegisterConfirmRoute;
use Contena\Core\System\Member\Channel\AbstractRegisterRoute;
use Contena\Core\System\Member\Exception\MemberAlreadyConfirmedException;
use Contena\Core\System\Member\Exception\MemberNotFoundByHashException;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Controller\Exception\FrontendException;
use Contena\Frontend\Framework\Guard\DoubleSubmitGuard;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Page\Account\Login\AccountLoginPageLoader;
use Contena\Frontend\Page\Account\MemberGroupRegistration\AbstractMemberGroupRegistrationPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a channel-api route to get or put data.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class RegisterController extends FrontendController
{
    /**
     * Scopes the double submit marker and lock of the registration submission.
     */
    public const DOUBLE_SUBMIT_SCOPE = 'frontend-registration';

    /**
     * @internal
     *
     * @param EntityRepository<ChannelDomainCollection> $domainRepository
     */
    public function __construct(
        private readonly AccountLoginPageLoader $loginPageLoader,
        private readonly AbstractRegisterRoute $registerRoute,
        private readonly AbstractRegisterConfirmRoute $registerConfirmRoute,
        private readonly SystemConfigService $systemConfigService,
        private readonly AbstractMemberGroupRegistrationPageLoader $memberGroupRegistrationPageLoader,
        private readonly EntityRepository $domainRepository,
        private readonly DoubleSubmitGuard $doubleSubmitGuard,
    ) {
    }

    #[Route(
        path: '/account/register',
        name: 'frontend.account.register.page',
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
        methods: [Request::METHOD_GET],
    )]
    public function accountRegisterPage(Request $request, RequestDataBag $data, ChannelContext $context): Response
    {
        if ($context->getMember()) {
            return $this->redirectToRoute('frontend.account.home.page');
        }

        $redirect = $request->query->get('redirectTo', 'frontend.account.home.page');
        $page = $this->loginPageLoader->load($request, $context);

        return $this->renderFrontend('@Frontend/frontend/page/account/register/index.html.twig', [
            'redirectTo' => $redirect,
            'redirectParameters' => $request->query->all()['redirectParameters'] ?? '[]',
            'errorRoute' => $request->attributes->get('_route'),
            'page' => $page,
            'data' => $data,
        ]);
    }

    #[Route(
        path: '/member-group-registration/{memberGroupId}',
        name: 'frontend.account.member-group-registration.page',
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
        methods: [Request::METHOD_GET],
    )]
    public function memberGroupRegistration(string $memberGroupId, Request $request, RequestDataBag $data, ChannelContext $context): Response
    {
        if ($context->getMember()) {
            return $this->redirectToRoute('frontend.account.home.page');
        }

        $redirect = $request->query->get('redirectTo', 'frontend.account.home.page');
        $page = $this->memberGroupRegistrationPageLoader->load($request, $context);

        $data->set('requestedGroupId', $memberGroupId);

        return $this->renderFrontend('@Frontend/frontend/page/account/member-group-register/index.html.twig', [
            'redirectTo' => $redirect,
            'redirectParameters' => $request->query->all()['redirectParameters'] ?? '[]',
            'errorRoute' => $request->attributes->get('_route'),
            'errorParameters' => json_encode(['memberGroupId' => $memberGroupId], \JSON_THROW_ON_ERROR),
            'page' => $page,
            'data' => $data,
        ]);
    }

    #[Route(path: '/account/register', name: 'frontend.account.register.save', defaults: [PlatformRequest::ATTRIBUTE_CAPTCHA => true], methods: [Request::METHOD_POST])]
    public function register(Request $request, RequestDataBag $data, ChannelContext $context): Response
    {
        if ($context->getMember()) {
            return $this->redirectToRoute('frontend.account.home.page');
        }

        try {
            $data->set('frontendUrl', $this->getConfirmUrl($context, $request));

            $additionalValidationDefinitions = $this->getAdditionalRegisterValidationDefinitions($data, $context);

            $this->doubleSubmitGuard->guard(self::DOUBLE_SUBMIT_SCOPE, $context, function () use ($data, $context, $additionalValidationDefinitions): void {
                $this->registerRoute->register(
                    $data->toRequestDataBag(),
                    $context,
                    false,
                    $additionalValidationDefinitions,
                );
            });
        } catch (ConstraintViolationException $formViolations) {
            if (!$request->request->has('errorRoute')) {
                throw RoutingException::missingRequestParameter('errorRoute');
            }

            if ($request->request->getString('errorRoute') === '') {
                $request->request->set('errorRoute', 'frontend.account.register.page');
            }

            $params = $this->decodeParam($request, 'errorParameters');

            return $this->forwardToRoute((string) RequestParamHelper::get($request, 'errorRoute'), ['formViolations' => $formViolations], $params);
        }

        if ($this->isDoubleOptIn($context)) {
            return $this->redirectToRoute('frontend.account.register.page');
        }

        return $this->createActionResponse($request);
    }

    #[Route(path: '/registration/confirm', name: 'frontend.account.register.mail', methods: [Request::METHOD_GET])]
    public function confirmRegistration(ChannelContext $context, QueryDataBag $queryDataBag): Response
    {
        if ($this->isHeadRequest()) {
            return new Response(status: Response::HTTP_NO_CONTENT);
        }

        try {
            $this->registerConfirmRoute->confirm($queryDataBag->toRequestDataBag(), $context);
        } catch (MemberNotFoundByHashException|MemberAlreadyConfirmedException|ConstraintViolationException) {
            $this->addFlash(self::DANGER, $this->trans('account.confirmationIsAlreadyDone'));

            return $this->redirectToRoute('frontend.account.register.page');
        }

        $this->addFlash(self::SUCCESS, $this->trans('account.doubleOptInRegistrationSuccessfully'));

        if ($redirectTo = $queryDataBag->get('redirectTo')) {
            $parameters = $queryDataBag->all();
            unset($parameters['em'], $parameters['hash'], $parameters['redirectTo']);

            return $this->redirectToRoute($redirectTo, $parameters);
        }

        return $this->redirectToRoute('frontend.account.home.page');
    }

    private function isDoubleOptIn(ChannelContext $context): bool
    {
        if (!$this->systemConfigService->get('core.loginRegistration.doubleOptInRegistration', $context->getChannelId())) {
            return false;
        }

        $this->addFlash(self::SUCCESS, $this->trans('account.optInRegistrationAlert'));

        return true;
    }

    private function getAdditionalRegisterValidationDefinitions(DataBag $data, ChannelContext $context): DataValidationDefinition
    {
        $definition = new DataValidationDefinition('frontend.confirmation');

        if ($this->systemConfigService->get('core.loginRegistration.requireEmailConfirmation', $context->getChannelId())) {
            $definition->add('emailConfirmation', new NotBlank(), new EqualTo(value: $data->get('email')));
        }

        if ($this->systemConfigService->get('core.loginRegistration.requirePasswordConfirmation', $context->getChannelId())) {
            $definition->add('passwordConfirmation', new NotBlank(), new EqualTo(value: $data->get('password')));
        }

        return $definition;
    }

    private function getConfirmUrl(ChannelContext $context, Request $request): string
    {
        $domainUrl = $this->systemConfigService->getString('core.loginRegistration.doubleOptInDomain', $context->getChannelId());
        if ($domainUrl) {
            return $domainUrl;
        }

        $domainUrl = $request->attributes->get(RequestTransformer::FRONTEND_URL);
        if ($domainUrl) {
            return $domainUrl;
        }

        $criteria = new Criteria()
            ->addFilter(new EqualsFilter('channelId', $context->getChannelId()))
            ->setLimit(1);

        $domain = $this->domainRepository->search($criteria, $context->getContext())->getEntities()->first();
        if (!$domain) {
            throw FrontendException::domainNotFound($context->getChannel());
        }

        return $domain->getUrl();
    }
}

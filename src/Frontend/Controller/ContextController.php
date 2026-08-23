<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Channel\AbstractContextSwitchRoute;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Framework\Routing\Router;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a channel-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class ContextController extends FrontendController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractContextSwitchRoute $contextSwitchRoute,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $router,
    ) {
    }

    #[Route(path: '/channel/configure', name: 'frontend.channel.configure', options: ['seo' => false], defaults: ['XmlHttpRequest' => true], methods: ['POST'])]
    public function configure(Request $request, RequestDataBag $data, ChannelContext $context): Response
    {
        $this->contextSwitchRoute->switchContext($data, $context);

        return $this->createActionResponse($request);
    }

    #[Route(path: '/channel/language', name: 'frontend.channel.switch-language', methods: ['POST'])]
    public function switchLanguage(Request $request, ChannelContext $context): RedirectResponse
    {
        $languageId = $request->request->get('languageId');
        if (!$languageId) {
            throw RoutingException::missingRequestParameter('languageId');
        }

        if (!\is_string($languageId) || !Uuid::isValid($languageId)) {
            throw RoutingException::invalidRequestParameter('languageId');
        }

        try {
            $newTokenResponse = $this->contextSwitchRoute->switchContext(
                new RequestDataBag([ChannelContextService::LANGUAGE_ID => $languageId]),
                $context,
            );
        } catch (ConstraintViolationException) {
            throw RoutingException::languageNotFound($languageId);
        }

        $params = $request->request->all()['redirectParameters'] ?? '[]';
        if (\is_string($params)) {
            try {
                $params = json_decode($params, true, flags: \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $params = [];
            }
        }

        $languageCode = $request->request->get('languageCode_' . $languageId);
        if ($languageCode) {
            $params['_locale'] = $languageCode;
        }

        $route = (string) $request->request->get('redirectTo', 'frontend.home.page');
        if ($route === '' || $this->routeTargetExists($route, $params) === false) {
            $route = 'frontend.home.page';
            $params = [];
        }

        if ($newTokenResponse->getRedirectUrl() === null) {
            return $this->redirectToRoute($route, $params);
        }

        $parsedUrl = parse_url($newTokenResponse->getRedirectUrl());

        if (!$parsedUrl) {
            throw RoutingException::languageNotFound($languageId);
        }

        $routerContext = $this->router->getContext();
        $routerContext->setHttpPort($parsedUrl['port'] ?? 80);
        $routerContext->setMethod('GET');
        $routerContext->setHost($parsedUrl['host'] ?? '');
        $routerContext->setBaseUrl(rtrim($parsedUrl['path'] ?? '', '/'));

        if ($this->requestStack->getMainRequest()) {
            $this->requestStack->getMainRequest()
                ->attributes->set(RequestTransformer::CHANNEL_BASE_URL, '');
        }

        $url = $this->router->generate($route, $params, Router::ABSOLUTE_URL);

        return new RedirectResponse($url);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function routeTargetExists(string $route, array $params): bool
    {
        try {
            $this->router->generate($route, $params);

            return true;
        } catch (RouteNotFoundException) {
            return false;
        }
    }
}

<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\Framework\Routing\RequestTransformerInterface;
use Contena\Core\PlatformRequest;
use Contena\Core\Profiling\Profiler;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Controller\Exception\FrontendException;
use Contena\Frontend\Event\FrontendRedirectEvent;
use Contena\Frontend\Event\FrontendRenderEvent;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Framework\Routing\Router;
use Contena\Frontend\Framework\Twig\Extension\IconCacheTwigFilter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

abstract class FrontendController extends AbstractController
{
    public const SUCCESS = 'success';
    public const DANGER = 'danger';
    public const INFO = 'info';
    public const WARNING = 'warning';

    public static function getSubscribedServices(): array
    {
        $services = parent::getSubscribedServices();

        $services['twig'] = Environment::class;
        $services['event_dispatcher'] = EventDispatcherInterface::class;
        $services[SystemConfigService::class] = SystemConfigService::class;
        $services[TemplateFinder::class] = TemplateFinder::class;
        $services[SeoUrlPlaceholderHandlerInterface::class] = SeoUrlPlaceholderHandlerInterface::class;
        $services[MediaUrlPlaceholderHandlerInterface::class] = MediaUrlPlaceholderHandlerInterface::class;
        $services['translator'] = TranslatorInterface::class;
        $services[RequestTransformerInterface::class] = RequestTransformerInterface::class;

        return $services;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function renderFrontend(string $view, array $parameters = []): Response
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();

        if ($request === null) {
            throw FrontendException::noRequestProvided();
        }

        $channelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_CHANNEL_CONTEXT_OBJECT);
        \assert($channelContext instanceof ChannelContext);

        $event = new FrontendRenderEvent($view, $parameters, $request, $channelContext);
        $this->container->get('event_dispatcher')->dispatch($event);

        $iconCacheEnabled = $this->getSystemConfigService()->get(
            'core.frontendSettings.iconCache',
            $event->getChannelContext()->getChannelId()
        ) ?? true;

        if ($iconCacheEnabled) {
            IconCacheTwigFilter::enable();
        }

        $response = Profiler::trace('twig-rendering', fn () => $this->render($view, $event->getParameters(), new Response()));

        if ($iconCacheEnabled) {
            IconCacheTwigFilter::disable();
        }

        $host = (string) $request->attributes->get(RequestTransformer::FRONTEND_URL);

        $seoUrlReplacer = $this->container->get(SeoUrlPlaceholderHandlerInterface::class);
        $mediaUrlReplacer = $this->container->get(MediaUrlPlaceholderHandlerInterface::class);
        $content = $response->getContent();

        if ($content !== false) {
            $content = $mediaUrlReplacer->replace($content);

            $response->setContent(
                $seoUrlReplacer->replace($content, $host, $channelContext)
            );
        }

        $response->headers->set('Content-Type', 'text/html');

        return $response;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function trans(string $snippet, array $parameters = []): string
    {
        return $this->container
            ->get('translator')
            ->trans($snippet, $parameters);
    }

    protected function createActionResponse(Request $request): Response
    {
        if (RequestParamHelper::get($request, 'redirectTo') || RequestParamHelper::get($request, 'redirectTo') === '') {
            $params = $this->decodeParam($request, 'redirectParameters');

            $redirectTo = RequestParamHelper::get($request, 'redirectTo');

            if ($redirectTo && \is_string($redirectTo)) {
                return $this->redirectToRoute($redirectTo, $params);
            }

            return $this->redirectToRoute('frontend.home.page', $params);
        }

        if (RequestParamHelper::get($request, 'forwardTo')) {
            $params = $this->decodeParam($request, 'forwardParameters');
            $forwardTo = RequestParamHelper::get($request, 'forwardTo');

            if (\is_string($forwardTo)) {
                return $this->forwardToRoute($forwardTo, [], $params);
            }
        }

        return new Response();
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $routeParameters
     */
    protected function forwardToRoute(string $routeName, array $attributes = [], array $routeParameters = []): Response
    {
        $router = $this->container->get('router');

        try {
            $url = $this->generateUrl($routeName, $routeParameters, Router::PATH_INFO);
        } catch (RouteNotFoundException $e) {
            throw FrontendException::routeNotFound($routeName, $e);
        }

        $method = $router->getContext()->getMethod();
        $router->getContext()->setMethod(Request::METHOD_GET);

        $route = $router->match($url);
        $router->getContext()->setMethod($method);

        $request = $this->container->get('request_stack')->getCurrentRequest();

        if ($request === null) {
            throw FrontendException::noRequestProvided();
        }

        $attributes = array_merge(
            $this->container->get(RequestTransformerInterface::class)->extractInheritableAttributes($request),
            $route,
            $attributes,
            ['_route_params' => $routeParameters, 'ct-skip-transformer' => true]
        );

        return $this->forward($route['_controller'], $attributes, $routeParameters);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeParam(Request $request, string $param): array
    {
        $params = RequestParamHelper::get($request, $param);

        if (\is_string($params)) {
            try {
                $params = json_decode($params, true, flags: \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $params = [];
            }
        }

        if ($params === null || \is_numeric($params)) {
            $params = [];
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function redirectToRoute(string $route, array $parameters = [], int $status = Response::HTTP_FOUND): RedirectResponse
    {
        $event = new FrontendRedirectEvent($route, $parameters, $status);
        $this->container->get('event_dispatcher')->dispatch($event);

        try {
            return parent::redirectToRoute($event->getRoute(), $event->getParameters(), $event->getStatus());
        } catch (RouteNotFoundException $e) {
            throw FrontendException::routeNotFound($route, $e);
        }
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function renderView(string $view, array $parameters = []): string
    {
        $view = $this->getTemplateFinder()->find($view);

        try {
            return $this->container->get('twig')->render($view, $parameters);
        } catch (LoaderError|RuntimeError|SyntaxError $e) {
            throw FrontendException::renderViewException($view, $e, $parameters);
        }
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function render(string $view, array $parameters = [], ?Response $response = null): Response
    {
        $content = $this->renderView($view, $parameters);

        $response ??= new Response();
        $response->setContent($content);

        return $response;
    }

    protected function getTemplateFinder(): TemplateFinder
    {
        return $this->container->get(TemplateFinder::class);
    }

    protected function getSystemConfigService(): SystemConfigService
    {
        return $this->container->get(SystemConfigService::class);
    }

    protected function isHeadRequest(): bool
    {
        return isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'HEAD';
    }
}

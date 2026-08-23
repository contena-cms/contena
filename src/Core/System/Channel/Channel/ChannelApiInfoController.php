<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Channel;

use Contena\Core\Framework\Api\ApiDefinition\DefinitionService;
use Contena\Core\Framework\Api\ApiDefinition\Generator\ChannelApiGenerator;
use Contena\Core\Framework\Api\Route\ApiRouteInfoResolver;
use Contena\Core\Framework\Api\Route\RouteInfo;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twig\Environment;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class ChannelApiInfoController
{
    /**
     * @internal
     *
     * @param array{administration?: string} $cspTemplates
     */
    public function __construct(
        protected DefinitionService $definitionService,
        private readonly Environment $twig,
        private readonly array $cspTemplates,
        private readonly ApiRouteInfoResolver $apiRouteInfoResolver,
    ) {
    }

    #[Route(
        path: '/channel-api/_info/openapi3.json',
        name: 'channel-api.info.openapi3',
        defaults: ['auth_required' => '%contena.api.api_browser.auth_required_str%'],
        methods: ['GET']
    )]
    public function info(Request $request): JsonResponse
    {
        $apiType = $request->query->getAlpha('type', DefinitionService::TYPE_JSON_API);

        $apiType = $this->definitionService->toApiType($apiType);
        if ($apiType === null) {
            throw RoutingException::invalidRequestParameter('type');
        }

        $data = $this->definitionService->generate(ChannelApiGenerator::FORMAT, DefinitionService::CHANNEL_API, $apiType);

        return new JsonResponse($data);
    }

    #[Route(
        path: '/channel-api/_info/open-api-schema.json',
        name: 'channel-api.info.open-api-schema',
        defaults: ['auth_required' => '%contena.api.api_browser.auth_required_str%'],
        methods: ['GET']
    )]
    public function openApiSchema(): JsonResponse
    {
        $data = $this->definitionService->getSchema(ChannelApiGenerator::FORMAT, DefinitionService::CHANNEL_API);

        return new JsonResponse($data);
    }

    #[Route(
        path: '/channel-api/_info/stoplightio.html',
        name: 'channel-api.info.stoplightio',
        defaults: ['auth_required' => '%contena.api.api_browser.auth_required_str%'],
        methods: ['GET']
    )]
    public function stoplightIoInfoHtml(Request $request): Response
    {
        $nonce = $request->attributes->get(PlatformRequest::ATTRIBUTE_CSP_NONCE);
        $apiType = $request->query->getAlpha('type', DefinitionService::TYPE_JSON_API);
        $response = new Response($this->twig->render(
            '@Framework/stoplightio.html.twig',
            [
                'schemaUrl' => 'channel-api.info.openapi3',
                'cspNonce' => $nonce,
                'apiType' => $apiType,
            ]
        ));

        $cspTemplate = trim($this->cspTemplates['administration'] ?? '');
        if ($cspTemplate !== '') {
            $csp = str_replace('%nonce%', $nonce, $cspTemplate);
            $csp = str_replace(["\n", "\r"], ' ', $csp);
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }

    #[Route(
        path: '/channel-api/_info/routes',
        name: 'channel-api.info.routes',
        defaults: ['auth_required' => '%contena.api.api_browser.auth_required_str%'],
        methods: ['GET']
    )]
    public function getRoutes(): JsonResponse
    {
        $endpoints = array_map(
            static fn (RouteInfo $endpoint) => ['path' => $endpoint->path, 'methods' => $endpoint->methods],
            $this->apiRouteInfoResolver->getApiRoutes(ChannelApiRouteScope::ID)
        );

        return new JsonResponse(['endpoints' => $endpoints]);
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Controller;

use Mcp\Server\Builder;
use Contena\Core\Framework\Mcp\AllowList\McpAllowlist;
use Contena\Core\Framework\Mcp\McpCapabilityCatalog;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Provides the list of registered MCP capabilities so the Admin UI can populate
 * the per-integration allowlist selector.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class McpToolListController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Builder $builder,
        private readonly McpCapabilityCatalog $catalog,
    ) {
    }

    #[Route(
        path: '/api/_action/mcp/tools',
        name: 'api.action.mcp.tools',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['integration_mcp.editor'],
        ],
        methods: ['GET'],
    )]
    public function list(): JsonResponse
    {
        $this->builder->build();

        return new JsonResponse($this->catalog->enrichedTools());
    }

    #[Route(
        path: '/api/_action/mcp/capabilities',
        name: 'api.action.mcp.capabilities',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['integration_mcp.editor'],
        ],
        methods: ['GET'],
    )]
    public function capabilities(): JsonResponse
    {
        $this->builder->build();

        return new JsonResponse([
            McpAllowlist::TOOLS => $this->catalog->enrichedTools(),
            McpAllowlist::RESOURCES => $this->catalog->enrichedResources(),
            McpAllowlist::PROMPTS => $this->catalog->enrichedPrompts(),
        ]);
    }
}

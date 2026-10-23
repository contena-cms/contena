<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Controller;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Mcp\AllowList\McpAllowlist;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\AdminUser\AdminUserCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Saves the per-admin-user MCP allowlist (tools, resources, prompts).
 *
 * Requires `api_action_user_mcp-allowlist` and `admin_user:update`, both of which
 * `users_and_permissions.editor` grants. The route writes an arbitrary `{adminUserId}`, so the
 * entity privilege has to be part of the gate: since the allowlist decides what a principal
 * may reach over MCP, writing someone else's is a permission change.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
class AdminUserMcpAllowlistController
{
    /**
     * @internal
     *
     * @param EntityRepository<AdminUserCollection> $adminUserRepository
     */
    public function __construct(
        private readonly EntityRepository $adminUserRepository,
    ) {
    }

    #[Route(
        path: '/api/_action/admin-user/{adminUserId}/mcp-allowlist',
        name: 'api.action.admin_user.mcp-allowlist',
        defaults: [
            'auth_required' => true,
            PlatformRequest::ATTRIBUTE_ACL => ['api_action_user_mcp-allowlist', 'admin_user:update'],
        ],
        methods: ['POST'],
    )]
    public function save(string $adminUserId, Request $request, Context $context): Response
    {
        $adminUser = $this->adminUserRepository
            ->search(new Criteria([$adminUserId]), $context)
            ->getEntities()
            ->first();

        if ($adminUser === null) {
            return new Response(null, Response::HTTP_NOT_FOUND);
        }

        $body = $request->toArray();

        if (!\array_key_exists('allowlist', $body)) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        $allowlist = $body['allowlist'];

        if ($allowlist !== null && !\is_array($allowlist)) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        if ($allowlist !== null && !McpAllowlist::isValidPayload($allowlist)) {
            return new Response(null, Response::HTTP_BAD_REQUEST);
        }

        // No SYSTEM_SCOPE wrapper: `mcp_allowlist` carries no WriteProtected flag (unlike `admin`
        // on the same definition), and escalating the scope would skip AclWriteValidator, which is
        // what enforces `admin_user:update` on the write itself.
        $this->adminUserRepository->update([
            ['id' => $adminUserId, 'mcpAllowlist' => $allowlist],
        ], $context);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}

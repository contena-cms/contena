<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Controller;

use Mcp\Server;
use Psr\Http\Message\ResponseInterface as PsrResponseInterface;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Mcp\Http\McpHttpTransportFactory;
use Contena\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;
use Contena\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Contena\Core\Framework\Mcp\Notification\McpSessionRegistry;
use Contena\Core\Framework\Mcp\RateLimit\McpRateLimiter;
use Contena\Core\Framework\Mcp\Session\McpSessionIdValidator;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Channel API entry point for the MCP protocol over HTTP.
 * This endpoint uses the normal Channel API access key and Channel context token
 * instead of Admin API OAuth/integration keys.
 *
 * No per-integration allowlist is applied here. The Admin API MCP endpoint
 * restricts capabilities per integration/user via McpAllowlistProvider, but
 * the Channel API is intentionally open: any authenticated Channel client
 * can access all registered Channel API MCP capabilities. Fine-grained access
 * control at the Channel level is a deliberate future extension point.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class ChannelApiMcpServerController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly Server $server,
        private readonly McpHttpTransportFactory $transportFactory,
        private readonly McpRateLimiter $rateLimiter,
        private readonly McpSessionIdValidator $sessionIdValidator,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?McpSessionRegistry $sessionRegistry = null,
        private readonly ?McpListChangedNotifier $listChangedNotifier = null,
    ) {
    }

    #[Route(
        path: '/channel-api/_mcp',
        name: 'channel-api.mcp.endpoint',
        defaults: ['auth_required' => true],
        methods: [Request::METHOD_GET, Request::METHOD_POST, Request::METHOD_DELETE, Request::METHOD_OPTIONS],
    )]
    public function handle(Request $request): Response
    {
        $this->sessionIdValidator->validate($request);
        $this->rateLimiter->enforceForChannelApi($request);

        $this->logger?->debug('Channel API MCP request', [
            'method' => $request->getMethod(),
            'clientIp' => $request->getClientIp(),
        ]);

        $psrResponse = $this->server->run($this->transportFactory->createTransport($request));
        $this->registerSession($psrResponse);
        $this->flushPendingToolsListChanged($request);

        return $this->transportFactory->createResponse($psrResponse);
    }

    /**
     * Emits a tools/listChanged for the current channel-api session when a tool asked for it (e.g.
     * contena-toolset-enable). Runs after {@see Server::run()} has persisted the SDK session, so
     * the queued notification is not overwritten and the client drains it on its next poll.
     */
    private function flushPendingToolsListChanged(Request $request): void
    {
        if ($this->listChangedNotifier === null) {
            return;
        }

        if (!$request->attributes->getBoolean(McpListChangedNotifier::PENDING_TOOLS_LIST_CHANGED_ATTRIBUTE)) {
            return;
        }

        $sessionId = $request->headers->get('Mcp-Session-Id') ?? '';
        if ($sessionId === '') {
            return;
        }

        $this->listChangedNotifier->notifySession(
            $sessionId,
            new McpListChangedNotificationSet(tools: true, resources: false, prompts: false),
        );
    }

    /**
     * Registers the MCP session id emitted on the initialize response so the channel-api
     * listChanged notifier can target this session (mirrors the Admin controller).
     */
    private function registerSession(PsrResponseInterface $psrResponse): void
    {
        if ($this->sessionRegistry === null) {
            return;
        }

        $sessionId = $psrResponse->getHeaderLine(PlatformRequest::HEADER_MCP_SESSION_ID);
        if ($sessionId === '') {
            return;
        }

        $this->sessionRegistry->register($sessionId);
    }
}

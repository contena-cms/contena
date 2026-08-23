# Channel API MCP Server

## Overview

Contena exposes a dedicated Model Context Protocol (MCP) server for the Channel API
at `/channel-api/_mcp`. This endpoint lets AI agents operate in a Channel and Member
context using standard Channel API credentials. No Admin API OAuth required.

It is separate from the Admin API MCP endpoint (`/api/_mcp`) and runs its own
capability registry, so plugins can register Channel API-specific tools, prompts,
and resources that are only visible in the Frontend context.

## Authentication

The endpoint accepts standard Channel API headers:

- `ct-access-key`: the Channel access key
- `ct-context-token`: the current Member session token (optional; anonymous context is used when absent)

Example MCP client configuration:

```json
{
  "mcpServers": {
    "contena-channel-api": {
      "url": "https://your-shop.example/channel-api/_mcp",
      "headers": {
        "ct-access-key": "SWSC...",
        "ct-context-token": "<member-context-token>"
      }
    }
  }
}
```

## Registering Capabilities

Tag your service with one of the following to auto-register it in the Channel API MCP server:

| Tag | Type | Attribute |
|-----|------|-----------|
| `contena.channel_api_mcp.tool` | Tool | `#[McpTool(...)]` |
| `contena.channel_api_mcp.prompt` | Prompt | `#[McpPrompt(...)]` |
| `contena.channel_api_mcp.resource` | Resource | `#[McpResource(...)]` |

These are **separate** from the Admin API tags (`mcp.tool`, `mcp.prompt`, `mcp.resource`).
A capability registered for the Channel API is not visible on the Admin API endpoint and vice versa.
Channel API MCP tool classes must live outside the `src/Core/Framework/Mcp` scan path
(e.g. `src/Core/System/Channel/Mcp/`) to avoid being picked up by the Admin API discovery.

### Example tool

```php
#[McpTool(name: 'my-blog-finder', description: 'Find Blog entries for the current Channel')]
class MyBlogFinderTool extends McpToolResponse
{
    public function __construct(
        private readonly ChannelApiMcpContextProvider $contextProvider,
        private readonly BlogListingDataLoader $listingLoader,
    ) {}

    public function __invoke(string $query): string
    {
        $context = $this->contextProvider->getChannelContext();
        if ($context === null) {
            return $this->error('No Channel context available.');
        }
        // use $this->listingLoader with $context
        return $this->success([...]);
    }
}
```

### Shared context interface

Both `McpContextProvider` (Admin API) and `ChannelApiMcpContextProvider` (Channel API)
implement `McpContextProviderInterface`. Tools that only need a `Context` object and
want to work in both API scopes should type-hint against the interface:

```php
public function __construct(
    private readonly McpContextProviderInterface $contextProvider,
) {}
```

### Protocol-level handlers

Register custom MCP protocol handlers (e.g. for `sampling/createMessage`) using the
Channel API-specific tags:

- `mcp.channel_api.request_handler`
- `mcp.channel_api.notification_handler`

These are intentionally separate from `mcp.request_handler` and `mcp.notification_handler`
used by the Admin API server, so protocol extensions can target a specific API scope
without affecting the other.

## Access Control

No per-client allowlist is applied on the Channel API endpoint, unlike the Admin API which
supports per-integration capability restrictions via `McpAllowlistProvider`. Any
authenticated Channel API client can access all registered Channel API MCP capabilities.
Fine-grained access control at the Channel or Member tier is a deliberate
future extension point.

## Rate Limiting

Every request is rate-limited via `McpRateLimiter` before the protocol runs. The Channel
API endpoint uses its own bucket (`mcp_channel_api`, configured under
`contena.api.rate_limiter` in `contena.yaml`), separate from the Admin API
(`mcp_admin_api`). The key is `channelId + ct-context-token`, falling back to the
client IP when no Channel context is present.

The Channel API limits are intentionally tighter than the Admin API: this endpoint is
public and the context token is cheap to rotate, so the effective protection is closer to
per-IP. When the limit is exceeded the endpoint returns HTTP 429 (`McpException::throttled`).
Per-tool limits and a `Retry-After` header are tracked as future improvements in
`src/Core/Framework/Mcp/AGENTS.md`.

## Browser-based Clients (CORS)

The endpoint supports browser-based MCP clients. The global CORS handling
(`CorsListener`) allows the `mcp-session-id` and `mcp-protocol-version` request headers
and exposes `mcp-session-id` on responses. A browser client can therefore read the
session ID from the `initialize` response header and send it on all subsequent requests.

## Sessions

The server assigns a session ID (UUID) on `initialize` and returns it in the
`mcp-session-id` response header. A malformed `mcp-session-id` request header is
rejected with HTTP 400 before it reaches the transport.

Session state uses the MCP SDK's in-memory session store by default, which does not
survive across PHP workers. For multi-worker or multi-server deployments, define the
`mcp.session.store` service with an implementation of
`Mcp\Server\Session\SessionStoreInterface` backed by shared storage (e.g. Redis); the
Channel API server builder picks it up automatically.

## Built-in Capabilities

| Name | Type | Description |
|------|------|-------------|
| `contena-channel-api-context` | Tool | Returns the current session metadata: Channel ID, context token, language, and Member authentication state |

## Known Limitations

### Cache bypass

MCP tools that call service-layer code directly (e.g. `BlogListingDataLoader`, route loaders)
bypass the HTTP-level full-page cache that normally sits in front of Channel API routes. This
is intentional. AI agents need fresh, consistent data. Tool authors should keep it in
mind when implementing tools that feed from high-traffic cached routes, as repeated MCP calls
will hit the database/service layer directly rather than the cache.

### No automatic discovery

There is currently no automatic discovery mechanism for AI agents visiting the default
Contena Frontend. A Frontend visitor does not automatically expose the MCP endpoint
URL, the Channel access key, or a usable context token to an external agent.

Missing pieces for full autonomous Frontend agent support:
- No `/.well-known/` advertisement of the MCP server URL or credentials
- No session-to-context-token bridge for Frontend PHP session visitors

This gap is addressed by the **UCP (Unified Commerce Platform) SDK**, which provides
the discovery and authentication flow on top of this endpoint. Without UCP, this endpoint
is most useful for:

- **Headless Frontend** setups where the client already holds a `ct-access-key` and `ct-context-token`
- **Developer tooling** with pre-configured credentials
- **Plugin development**: build and test Channel API MCP tools locally before UCP integration

### No Frontend session bridge

A visitor on the default Contena Frontend has a PHP session but no `ct-context-token`
that an external agent can directly use. A future integration (e.g. a JavaScript snippet
embedding the current session's context token) would be needed to bridge this gap for
embedded AI experiences directly inside the Frontend.

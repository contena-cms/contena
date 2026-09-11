# Contena MCP Server

## Overview
This module implements a Model Context Protocol (MCP) server for Contena, enabling AI clients (e.g., Claude Desktop, Cursor) to interact with the Contena platform through a standardized protocol.

## Status
Stable in the Contena 6.8 baseline. It is always enabled and has no feature flag.

## MCP capabilities

The MCP protocol defines three capability types. Each serves a different purpose:

### Tools
Actions the AI client can invoke. Tools execute logic and return results. Think of them as API endpoints the AI can call.
- **Triggered by**: the AI client decides when to call them
- **Can have side effects**: yes (writes, deletes, command execution)
- **Examples**: `contena-entity-search`, `contena-entity-upsert`, `contena-system-config-write`
- **Attribute**: `#[McpTool(name: '...', description: '...')]`
- **Implementation**: `__invoke()` returns a JSON string

### Prompts
Pre-written instructions the AI client can request to get context. Prompts help the AI understand the system before it starts working.
- **Triggered by**: the AI client requests them during setup or when it needs guidance
- **Can have side effects**: no, read-only text
- **Examples**: `contena-context` -- explains the data model, criteria format, and best practices
- **Attribute**: `#[McpPrompt(name: '...', description: '...')]`
- **Implementation**: `__invoke()` returns an array of `['role' => '...', 'content' => '...']` messages

### Resources
Static data the AI client can read. Resources are identified by URIs and provide reference data without executing logic.
- **Triggered by**: the AI client reads them like files
- **Can have side effects**: no, read-only data
- **Examples**: `contena://entities`, `contena://channels`, `contena://state-machines`, `contena://business-events`, `contena://flow-actions`
- **Attribute**: `#[McpResource(uri: '...', name: '...', description: '...')]`
- **Implementation**: `__invoke()` returns `['uri' => '...', 'mimeType' => '...', 'text' => '...']`

### When to use which
| Need | Use |
|---|---|
| AI should be able to query/modify data | Tool |
| AI needs instructions on how to use the system | Prompt |
| AI needs static reference data (lists, schemas) | Resource |

## Tool discovery: guaranteed vs best-effort

The Admin API endpoint uses progressive disclosure: `tools/list` advertises only a small set (`contena-tool-search`, `contena-toolsets-list`, `contena-toolset-enable`, plus any session-enabled toolsets), not the full catalogue. There are two ways to reach a hidden tool, and they are not equivalent:

- **`contena-toolset-enable` + `listChanged` (guaranteed).** Enabling a toolset stores it on the session, advertises its tools in `tools/list`, and emits a `tools/listChanged` notification. Any spec-compliant client refreshes `tools/list` and can then call the tools. This path works on every client and is the one to rely on.
- **`contena-tool-search` inline definitions (best-effort).** Search returns full tool definitions inline so a capable client can call them immediately without enabling anything. This only works if the client promotes inline results into its callable set (Anthropic's tool-search-capable clients do; many others do not). `tools/call` itself never blocks an allowlisted tool for being unadvertised, so the server never dead-ends — but a client that treats `tools/list` as the immutable callable set will loop. The admin `contena-tool-search` result therefore carries a `_meta.usage` hint pointing at the enable path as the fallback.

Every registered tool belongs to a group, and group membership is the single source of truth for visibility. The `discovery` group holds the always-advertised meta-tools (`contena-tool-search`, `contena-toolsets-list`, `contena-toolset-enable`) and is never an enable-able toolset; it is the only thing on a fresh `tools/list`. Every other tool is **deferred** — advertised only once its toolset is enabled — so no domain tool can leak into the default surface and the model is forced through discovery. Core and plugin tools declare their group with `#[McpToolGroup]` at compile time (`McpToolDiscoveryCompilerPass` derives `contena.mcp.advertised_tools` from the `discovery` group); app tools (loaded at runtime, so they carry no attribute) are grouped under their owning app's technical name via `AppMcpPrivilegeProvider::getAppToolGroups()`, so each app forms its own toolset. Anything still without a group falls to the `other` catch-all, which is itself an enable-able toolset so that no allowlisted tool is ever reachable through `contena-tool-search` alone.

The Channel API endpoint (`/channel-api/_mcp`) uses the same progressive disclosure via its own toolset meta-tools (`ChannelApiToolsetsListTool` / `ChannelApiToolsetEnableTool`) and a `channel-api` toolset group, and its `contena-tool-search` carries the same `_meta.usage` hint. Both endpoints' server `instructions` (returned in every `initialize` response) point clients at `contena-tool-search` as the discovery entry point when no advertised tool matches the requested action.

## Architecture
- **Transport**: HTTP via Symfony MCP Bundle (`/api/_mcp`), authenticated through Contena's Admin API OAuth stack
- **Context**: `McpContextProvider` bridges the authenticated HTTP request into the MCP tool execution layer
- **Tools**: Single-responsibility PHP classes with `#[McpTool]` attributes, registered via PHP service definitions (`mcp.php`)
- **Availability**: always enabled (no feature flag); services are present whenever `symfony/mcp-bundle` is installed

## Naming convention
All capability names use hyphen-separated prefixes (`a-zA-Z0-9_-` only, no dots):
- **Core**: `contena-{name}` (e.g., `contena-entity-search`, `contena-entity-upsert`)
- **Plugin**: `{plugin-name}-{capability-name}` (e.g., `acme-content-publisher`)
- **App**: `{app-name}-{capability-name}` (e.g., `my-cms-publish-content`)

`McpToolDiscoveryCompilerPass` enforces unique names per server and throws on conflicts. The `contena-` prefix is reserved for core tools; `AppMcpToolLoader` skips app tools whose computed name starts with `contena-`.

## Folder structure
- `AllowList/` -- Per-integration capability allowlist (`McpAllowlistProvider`, `McpAllowlistFilter`, `McpAllowlist`)
- `Attribute/` -- MCP-specific PHP attributes (`#[McpToolDependsOn]`, `#[McpToolRequires]`)
- `Authentication/` -- MCP authentication and exception listeners
- `Context/` -- Context bridging (`McpContextProvider`, `ChannelApiMcpContextProvider`)
- `Controller/` -- HTTP endpoints for MCP protocol (`McpServerController` admin, `ChannelApiMcpServerController` channel)
- `RateLimit/` -- `McpRateLimiter` wrapper around the core `RateLimiter` (per-scope keys + throttle translation)
- `Session/` -- Session helpers: `McpSessionIdValidator` (rejects malformed `mcp-session-id`), `McpSessionCleanupSubscriber` (wipes tool-result cache on session DELETE)
- `Tool/` -- Individual MCP tool implementations
- `Prompt/` -- System prompts for AI context
- `Resource/` -- Static MCP resources
- `Command/` -- CLI commands (`debug:mcp`)
- `Loader/` -- Extension loaders for app capabilities (`AppMcpToolLoader`, `AppMcpPromptLoader`, `AppMcpResourceLoader`, `AppMcpCapabilityExecutor`, `AbstractAppMcpLoader`)
- `docs/` -- Documentation: tool reference, examples, security, setup, extensibility, user stories

## Availability

The MCP server is always enabled. `symfony/mcp-bundle` and `mcp/sdk` are required runtime dependencies in the Contena 6.8 baseline.

## Conventions
- Tools return JSON strings; the MCP protocol handles transport encoding
- Write tools default to `dryRun=true` for safety. Dry-run adds `SKIP_TRIGGER_FLOW` to the context to prevent Flow Builder actions during preview
- Entity tools validate entity existence with `registry->has()` before ACL checks to provide clear error messages
- Service IDs use FQCN; tags include `mcp.tool` for SDK discovery
- Tools declare prerequisites with `#[McpToolDependsOn('other-tool-name')]` (repeatable) — the allowlist UI auto-expands these when a user enables a tool; `debug:mcp` shows them in the Dependencies column
- Tools declare required ACL privileges with `#[McpToolRequires]` (repeatable) — **declarative only**; runtime enforcement still depends on `requirePrivilege()` calls and DAL ACL checks. The attribute is used by `debug:mcp` (Privileges column), the API (`/_action/mcp/tools`), and the Admin UI to help operators configure roles correctly

### Tool metadata: `meta` vs dedicated attributes

Avoid adding a new PHP attribute for every MCP tool hint. Choose the smallest representation that keeps the concept clear and maintainable:

- Use `#[McpTool(..., meta: [...])]` for lightweight MCP descriptor hints that are simple scalar values, experimental, client-facing, or only consumed near tool discovery/advertisement. Examples: ranking/search hints, visibility hints. (Advertisement visibility itself is **not** a `meta` hint — it is derived from the `#[McpToolGroup]`; the `discovery` group is advertised, everything else is deferred.)
- Use a dedicated Contena attribute only when the concept is first-class in Contena, needs structured typing or validation, is repeatable, is consumed by several subsystems, or should be discoverable without parsing arbitrary string keys. Examples: `#[McpToolDependsOn]` and `#[McpToolRequires]`.
- Do not duplicate the same concept in both places. If `meta` grows validation rules, multiple consumers, or cross-cutting behavior, consider promoting it to an attribute in a follow-up. If an attribute is just a single optional scalar with one consumer, prefer `meta` instead.
- Before adding a new attribute, document why `meta` is not enough in the PR description or nearby tests. Attribute sprawl makes tool declarations harder to scan.

## Validating capabilities are loaded

Two things have to hold: the service carries the DI tag, and it is assigned to a server.

### One layer: the DI tag
The DI tag is all that is required, for core, in-tree bundle, plugin and third-party bundle tools alike. The MCP bundle collects every service tagged `mcp.tool` at container compile time, reads its `#[McpTool]` attribute and registers it on a server. There is no directory scanning: `scan_dirs` was removed when the bundle replaced the SDK's file-based discovery with compile-time container registration (mcp-bundle 0.12).

Plugin lifecycle is respected: if the plugin is inactive the service is absent from the container and the tool is not registered.

### Which server a tool lands on
Each MCP server declares in `packages/mcp.php` which capabilities it exposes, as namespace prefixes — `Contena\Core\Framework\Mcp\` and `Contena\Frontend\Mcp\` for the Admin API server, `Contena\Core\System\Channel\Mcp\` for the Channel API one. A capability outside those namespaces (any plugin or third-party bundle) cannot be named by a prefix, so `McpToolDiscoveryCompilerPass` assigns it explicitly: it appends the class to the bundle's `mcp.servers.elements` parameter, plugin capabilities going to the Admin API server. A capability assigned to no server is silently absent; both `bin/console debug:mcp` (in its footer) and `bin/console debug:mcp --native` report those.

### Verification methods

| Method | What it covers | When to use |
|---|---|---|
| `bin/console debug:mcp` | Both registries (admin + channel-api) — same source as the HTTP endpoints | Quick manual check during development |
| `bin/console debug:mcp --native` | The MCP bundle's own view: configured servers and clients, prompts and resources, and capabilities assigned to no server | Checking a prompt or resource, or why a capability does not show up |
| `McpCapabilityDiscoveryTest` | HTTP → `tools/list` (full kernel) | CI — authoritative end-to-end check |
| `McpServiceRegistrationTest` | DI layer only | Fast integration-level guard that every MCP service is registered in the container |

`bin/console debug:mcp` uses the same `Registry` instances as the HTTP endpoints (populated by calling `Builder::build()` per scope), so it shows core tools, plugin tools, app tools, and Channel API tools in one view, grouped per endpoint. It lists tools only, with the Contena data the bundle's command has no equivalent for; prompts and resources are listed by `--native`. Use `--scope=api` or `--scope=channel-api` to narrow it to one endpoint.

**`McpCapabilityDiscoveryTest`** (`tests/integration/Core/Framework/Mcp/McpCapabilityDiscoveryTest.php`) boots the full kernel, authenticates, and calls the live MCP HTTP endpoint. It is the authoritative check that mirrors what the MCP Inspector does interactively. Add new capability names to its `expectedTools()` / `expectedPrompts()` / `expectedResources()` lists when adding new core capabilities.

## Extensibility
- **Plugins**: Tag services with `contena.mcp.tool` -- `McpToolDiscoveryCompilerPass` re-tags them as `mcp.tool` and assigns them to the Admin API server, so they appear in both `debug:mcp` and the HTTP endpoint. Use `McpToolResponse` for consistent error handling and response formatting.
- **Third-party Symfony bundles**: Same `contena.mcp.tool` tag mechanism as plugins -- `McpToolDiscoveryCompilerPass` handles it. See `custom/bundles/CtagMcpExampleBundle/` for a worked example.
- **Apps**: Declare capabilities in `Resources/mcp.xml` -- parsed by `Mcp::createFromXmlFile()` (XXE-safe via `XmlUtils::loadFile()`), persisted by the respective Persister (`McpToolPersister`, `McpPromptPersister`, `McpResourcePersister`), loaded at runtime by the corresponding Loader (`AppMcpToolLoader`, `AppMcpPromptLoader`, `AppMcpResourceLoader`). App tool webhook payloads include `installationId` and `appVersion` in the `source` object. **App tools also support internal dispatch via `/api/script/{path}` -- see the Serverless app tools section below.**
- **In-tree Contena bundles** (Frontend, etc.): Tag with **`mcp.tool`** directly (not `contena.mcp.tool`), and make sure the class sits under a namespace the Admin API server's `registry` prefixes in `packages/mcp.php` cover -- otherwise add it there.
- **Reserved prefix**: The `contena-` prefix is reserved for core tools. App tools with names starting with `contena-` are skipped during loading.

## Serverless app tools (app scripts)

App MCP tools can use `/api/script/{path}` as their `url` — Contena dispatches these as internal subrequests, removing the need for an external server or `<setup>` registration handshake.

### How it works

1. `AppMcpCapabilityExecutor` detects URLs starting with `/` and dispatches a Symfony subrequest instead of a Guzzle HTTP call.
2. Arguments are passed as a `POST` form parameter named `arguments` (not JSON body), so Twig scripts can access them via `hook.request.request.all('arguments')`.
3. Auth headers from the parent MCP request are inherited — the subrequest runs in the integration's authenticated context, so DAL ACL is enforced normally.
4. `AppMcpToolLoader` SQL includes apps without a secret when their tool URL starts with `/`.

### App script pattern

In `manifest.xml`, no `<setup>` block is needed. Declare entity permissions normally in `<permissions>`.

In `Resources/mcp.xml`, set `url="/api/script/{path}"` and optionally declare `<required-privileges>`:

```xml
<mcp-tool name="my-tool" url="/api/script/my-app-my-tool">
    <label>My Tool</label>
    <required-privileges>
        <privilege>blog:read</privilege>
        <privilege>category:read</privilege>
    </required-privileges>
</mcp-tool>
```

Create the Twig script at `Resources/scripts/api-my-app-my-tool/script.twig`:

```twig
{% block response %}
    {% set args = hook.request.request.all('arguments') ?? {} %}
    {% set response = services.response.json({ success: true, data: { ... } }) %}
    {% do hook.setResponse(response) %}
{% endblock %}
```

### Caching

**Always use `/api/script/` (Admin API), never `/channel-api/script/`.** Admin API scripts are POST-only, auth-required, and never HTTP-cached — correct for MCP tools where AI agents must receive current data. Channel API scripts use Contena's HTTP cache layer; if you must use one for MCP, add `{% do response.cache.disable() %}` before `hook.setResponse()` to prevent stale responses.

### Required privileges (`<required-privileges>`)

`<required-privileges>` in `mcp.xml` is the app-side equivalent of `#[McpToolRequires]` for plugins. It is **informational** — the Admin UI shows the declared privileges as chips in the integration allowlist and warns when the integration role is missing them. Actual enforcement happens via DAL ACL inside the script.

`McpToolPersister` validates at install/update time that every declared privilege appears in the app's manifest `<permissions>`. An app that declares `blog:read` as a required privilege but does not have `<read>blog</read>` in `<permissions>` will fail to install with a clear error message.

## Future ideas / backlog

### ACL / visibility
- **Filter `contena://entities` resource by ACL** — `EntityListResource` currently returns all registered entities regardless of the caller's permissions. It should inject `McpContextProvider` and filter by `$context->isAllowed($entity . ':read')`, with a null-safe fallback for CLI/system contexts (return full list when there is no HTTP request).
- **`debug:mcp` entity visibility** — when `--integration CTIA...` is passed, add an "Entities" count column to the tools table (how many entities that integration can read for entity-tools). In the detail view (`debug:mcp contena-entity-read --integration ...`), show the full sorted list of accessible entity names.

### Rate limiting
The two MCP endpoints are rate-limited via the core `RateLimiter` through `McpRateLimiter` (`RateLimit/McpRateLimiter.php`), which owns the throttle/`McpException::throttled()` translation and the per-scope key derivation. Each scope has its own config route: `mcp_admin_api` (keyed per OAuth token, generous) and `mcp_channel_api` (keyed per channel context token, tighter because it is public and the key is rotatable). Both are defined in `contena.yaml` under `contena.api.rate_limiter`.

Open improvements:
- **Per-tool rate limiting** — the current limit is per-endpoint: a cheap `tools/list` and an expensive `entity-upsert`/`entity-delete`/`media-upload`/`system-config-write` draw from the same bucket. The industry consensus for MCP servers is to bucket cheap reads (`entity-search`, `entity-schema`) high (~100-200/min) and expensive/mutating tools low (~5-30/min). This needs a per-tool key (e.g. derive the tool name from the JSON-RPC body, which `McpServerController` already parses into `ATTRIBUTE_JSONRPC_BODY`) and a route per cost class.
- **`Retry-After` header** — `McpException::throttled()` returns HTTP 429 with the wait time in the message/parameters, but no `Retry-After` response header. Adding it (and a structured `retry_after` in the JSON-RPC error) lets well-behaved agent clients back off correctly instead of blindly retrying.
- **`time_backoff` vs token bucket** — Contena uses the `time_backoff` policy (escalating penalty on repeated hits), not a token bucket, so it does not give the "burst then sustained" shape the MCP guides recommend for bursty agent traffic. Acceptable as a coarse circuit breaker; revisit if agents hit the limit on legitimate fan-out.

### Channel API MCP
The Channel API endpoint is available at `/channel-api/_mcp`. It authenticates with the channel access key and context token, owns an isolated registry and session store, and exposes generic channel/member-context capabilities. Content discovery and account-oriented tools can be added without introducing commerce workflows.

### SDK-ready features (no upstream changes needed)
The symfony-mcp-bundle (v0.13.0) and mcp/sdk (v0.8.1) provide the following building blocks:

- **`listChanged` notifications** — SDK has `ToolListChangedNotification`, `ResourceListChangedNotification`, `PromptListChangedNotification` in `vendor/mcp/sdk/src/Schema/Notification/`. Call `$protocol->sendNotification()` from an event listener when capabilities change (e.g. after app install/uninstall). Lets AI clients refresh their tool list without reconnecting.
- **Resource subscriptions** — SDK has `ResourceSubscribeHandler` and `ResourceUnsubscribeHandler` (`vendor/mcp/sdk`). Resource templates (`#[McpResourceTemplate]`) are already wired up in core — see `ToolResultResource` and `Resource/AGENTS.md`. Subscriptions remain to be wired up if clients need push notifications when resources change.
- **Protocol-level pagination** — `RegistryInterface::getTools(?int $limit, ?string $cursor)` etc. already support cursor-based pagination; bundle has a `mcp.pagination_limit` config param. Contena doesn't configure or expose it — relevant once tool/resource counts grow large.
- **Completion utility** — SDK has `CompletionCompleteHandler` + `CompletionProvider` interface with built-in `EnumCompletionProvider` / `ListCompletionProvider` (`vendor/mcp/sdk/src/Capability/Attribute/CompletionProvider.php`). Register providers on tool/prompt arguments to power autocomplete in MCP clients (e.g. entity name suggestions for the `entity` parameter on entity tools).

---

## Security

Every MCP request passes through three layers in order:

1. **Authentication** — `ct-access-key` + `ct-secret-access-key` headers required on every request
2. **Per-integration capability allowlist** — each integration stores a `mcp_allowlist` JSON object with `tools`, `resources`, and `prompts` keys (null per key = unrestricted; empty array = deny all). Configured via Settings → Integrations → Edit MCP Allowlist. `tools/list`, `resources/list`, and `prompts/list` responses are filtered; `tools/call`, `resources/read`, and `prompts/get` are rejected early with a clear error. Tool allowlist auto-expands transitive `#[McpToolDependsOn]` dependencies. **The `admin` flag does NOT bypass this layer** — it only bypasses layer 3 (ACL). **Scope**: enforced only for integration-authenticated requests (`ct-access-key` + `ct-secret-access-key`, or OAuth `client_credentials` for an integration key). Admin user bearer tokens issued via password/refresh grant (`client_id = administration`) resolve to no integration row in `McpAllowlistProvider::forAccessKey()` and fall back to unrestricted — the allowlist is effectively skipped for them.
3. **ACL / Privileges** — tools call `requirePrivilege()` before touching data. Missing privileges return `{"success": false, "error": "Missing privilege: ..."}` (single canonical prefix — use `McpToolResponse::missingPrivilegesError()`, never a hand-rolled message). Entity tools that accept criteria JSON additionally validate the built `Criteria` with `AclCriteriaValidator` (same association ACL model as the Admin API), so reading, filtering, or aggregating over an association also requires the associated entity's `:read` privilege. Tools may also annotate their static requirements with `#[McpToolRequires]` so operators can configure roles correctly upfront — but this is informational only and does not replace the `requirePrivilege()` check.

Additional safeguards:
- **Rate limiting**: every request passes through `McpRateLimiter` before the protocol runs. Separate per-scope buckets (`mcp_admin_api`, `mcp_channel_api`); exceeding the limit returns HTTP 429 via `McpException::throttled()`. See the Rate limiting section under "Future ideas / backlog" for the keying details and open improvements.
- **Audit logging**: tool invocations logged via `mcp` Monolog channel
- **App HMAC**: app tool calls signed with `RequestSigner` using the app secret
- **XML parsing**: `mcp.xml` parsed with `XmlUtils::loadFile()` to prevent XXE attacks
- **Entity validation**: entity tools check `registry->has()` before ACL to give clear "entity not found" errors
- **Global compile-time allowlist**: `contena.mcp.allowed_tools` acts as an installation-wide safety switch (secondary to per-integration allowlists)
- **Error visibility**: `McpExceptionListener` converts exceptions on the MCP route to JSON-RPC error responses instead of HTML. It also handles `POST /register` — some clients (e.g. Cursor) fall back to that path when the primary connection fails; without the listener they receive an HTML 404 or a frontend redirect that hides the real error. The gate is `POST` method (not `Accept: application/json`) since browser navigation to a register page uses GET.

## Admin UI — integration list

App integrations (created on app install via `AppLifecycle::enrichInstallMetadata`) appear in Settings → Integrations with a "Managed by App" badge. Edit and Delete are disabled for them; only the MCP tool allowlist is editable.

**ACL privileges:**
- `integration.editor` — gates creating/editing/deleting manual integrations (label, keys, ACL roles)
- `integration_mcp.editor` — gates editing the MCP capability allowlist for any integration. Declared under `additional_permissions` in `acl/index.js`. Depends on `integration.viewer`.

**Saving the allowlist** uses a dedicated endpoint `POST /api/_action/integration/{id}/mcp-allowlist` (controller: `IntegrationMcpAllowlistController`). Body: `{ allowlist: {tools, resources, prompts} | null }`. This avoids the changeset generator recursing into the `app` one-to-one association (which has no `_origin` when loaded via criteria) and provides a clean ACL boundary separate from `integration:update`.

**App deactivation**: when an app is deactivated via `AppLifecycle::deactivate()`, its integration is soft-deleted (`deletedAt` set). This suspends MCP authentication for that integration — the DAL excludes soft-deleted rows, so token requests fail. Reactivating the app (`activate()`) clears `deletedAt` and restores access.

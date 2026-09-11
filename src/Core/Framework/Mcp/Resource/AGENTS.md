# MCP Resources

## Purpose
MCP resources expose read-only data that AI clients can request. Unlike tools, resources are static data endpoints identified by URIs.

## Current resources
- `EntityListResource` (`contena://entities`) -- lists all registered entity names
- `BusinessEventsResource` (`contena://business-events`) -- all business events that can trigger flows
- `ExtensionsResource` (`contena://extensions`) -- installed plugins and apps with name, version, and active state
- `FlowActionsResource` (`contena://flow-actions`) -- all flow actions available in Flow Builder
- `ChannelListResource` (`contena://channels`) -- channels with IDs, names, domains
- `LanguageListResource` (`contena://languages`) -- languages with locale codes
- `StateMachineResource` (`contena://state-machines`) -- state machines with states and transitions

## Resource templates (dynamic URIs)

Static resources have a fixed URI. Resource templates use a URI pattern with placeholders (e.g. `contena://tool-result/{id}`) so a single handler serves many concrete resources.

- `ToolResultResource` (`contena://tool-result/{id}`) reads oversized tool results stored in `mcp_tool_result_cache` by `McpToolResponse::success()`. The handler accepts the `{id}` placeholder plus the SDK's `RequestContext` to scope reads to the current MCP session.

Templates are registered with the `mcp.resource_template` DI tag (not `mcp.resource`) and use the `#[McpResourceTemplate(uriTemplate: ..., name: ..., description: ..., mimeType: ...)]` attribute. The template name must match `[a-zA-Z0-9_-]+` and the URI template must contain at least one placeholder.

`debug:mcp --native` lists templates in a separate **Resource Templates** section (`debug:mcp` itself lists tools only). MCP clients discover them via `resources/templates/list` (not `resources/list`).

## Adding a resource
1. Create a class with `#[McpResource(uri: '...', name: '...', description: '...')]` on the class
2. Return an array with `uri`, `mimeType`, and `text` keys from `__invoke`
3. Register in `mcp.php` with `mcp.resource` and `contena.feature` tags

## Adding a resource template
1. Create a class with `#[McpResourceTemplate(uriTemplate: 'contena://...{id}', name: '...', description: '...', mimeType: '...')]` on the class
2. The `__invoke` method receives placeholder values as parameters (matched by name) plus optionally a `Mcp\Server\RequestContext` for session/request access
3. Throw `McpException::toolResultNotFound($id)` (or any `Mcp\Exception\ResourceNotFoundException` factory) on missing data so the SDK returns a proper not-found error
4. Register in `mcp.php` with `mcp.resource_template` and `contena.feature` tags

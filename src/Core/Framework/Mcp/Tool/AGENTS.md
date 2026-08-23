# MCP tools

Each file in this directory implements one action an AI client can invoke.

## Patterns

- Use a `#[McpTool(name: 'contena-...', description: '...')]` attribute on the class.
- Extend `McpToolResponse` and return `$this->success()` or `$this->error()` from `__invoke()`.
- Inject dependencies through the constructor and obtain the authenticated `Context` from `McpContextProvider`.
- Write operations must default to `dryRun=true`; use the shared transaction helper so previews do not persist data or trigger flows.
- Validate entity existence before ACL checks to return a useful error.
- Validate criteria JSON with `AclCriteriaValidator` before DAL access so association reads enforce the associated entity's `:read` privilege.
- Serialize DAL data with `JsonEntityEncoder` and apply `McpEntityIncludes` defaults so responses remain bounded.
- Use `#[McpToolDependsOn]` only for genuine prerequisites. Use `#[McpToolRequires]` for declarative ACL metadata, while enforcing the privilege at runtime as well.

## AI-facing descriptions

The `description` is a routing contract. Lead with the user's likely trigger phrases, distinguish similar tools explicitly, and keep optional parameters genuinely optional. Do not describe workflows or entities that are not part of generic Contena Administration.

## Response conventions

Successful responses contain `success: true` and `data`; optional `_meta` carries pagination, dry-run, or response-size information. Errors contain `success: false` and a human-readable `error`. Oversized responses are stored in the session-scoped result cache and returned through `contena://tool-result/{id}`.

## Current core tools

- `contena-entity-schema`, `contena-entity-search`, `contena-entity-aggregate`, and `contena-entity-read` inspect DAL data.
- `contena-entity-upsert` and `contena-entity-delete` perform safe previewable writes.
- `contena-system-config-read` and `contena-system-config-write` access global or channel-scoped configuration.
- `contena-media-upload` uploads media.
- `contena-tool-search`, `contena-toolsets-list`, and `contena-toolset-enable` provide progressive discovery.

## Adding a tool

1. Add the class and MCP attributes.
2. Register it in `DependencyInjection/mcp.php` with `mcp.tool`.
3. Add unit coverage under `tests/unit/Core/Framework/Mcp/Tool/`.
4. Add the name to the HTTP discovery expectations when it is a core capability.
5. Clear the cache and verify `php bin/console debug:mcp` and the live `/api/_mcp` endpoint.

Do not add App loaders, Channel API transports, Channel context, Blog workflow shortcuts, or other Commerce-only tools to this directory.

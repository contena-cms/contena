# Contena MCP Server

## Overview

This module implements Contena's experimental Model Context Protocol server for authenticated Administration automation. It exposes generic platform capabilities through the Symfony MCP Bundle and the MCP SDK.

The supported public endpoint is `/api/_mcp`. The server is always enabled when `symfony/mcp-bundle` is installed; it is not controlled by a feature flag. Classes remain experimental until the stated stable version and may change before then.

## Capabilities

MCP has three capability types:

- **Tools** perform actions and may write or delete data. They use `#[McpTool]` and an `__invoke()` method.
- **Prompts** provide read-only system guidance to an AI client. They use `#[McpPrompt]`.
- **Resources** provide read-only reference data through a URI. They use `#[McpResource]` or `#[McpResourceTemplate]`.

The current core surface is intentionally generic: DAL entity operations, global SystemConfig, media upload, language and state-machine reference data, capability discovery, session state, and extension metadata. Domain-specific commerce, Frontend, Channel, order, Blog, and app capabilities are not part of this module.

## Progressive discovery

Fresh sessions advertise only the discovery tools: `contena-tool-search`, `contena-toolsets-list`, and `contena-toolset-enable`. Other tools are deferred until a toolset is enabled. Enabling a toolset persists the choice for the MCP session and emits `tools/listChanged`; this is the portable path for clients that treat `tools/list` as their callable set. `contena-tool-search` may return inline definitions as a best-effort shortcut.

Every tool belongs to a group. `#[McpToolGroup]` is the source of truth for core and plugin tools; the `discovery` group is always advertised and is not enable-able. Ungrouped tools fall into the enable-able `other` group.

## Architecture

- **Transport**: Streamable HTTP through Symfony MCP Bundle at `/api/_mcp`.
- **Authentication**: Contena Administration OAuth and ACL middleware.
- **Context**: `McpContextProvider` bridges the authenticated request `Context` into tool execution.
- **Tools**: single-purpose PHP services with `#[McpTool]`, registered in `DependencyInjection/mcp.php`.
- **Allowlist**: per-user and per-integration restrictions for tools, resources, and prompts.
- **Sessions**: session identifiers, toolset state, oversized-result cache, cleanup, and list-changed notifications.

## Naming

Core capability names use the `contena-` prefix and kebab case, for example `contena-entity-search`. Plugin and Bundle capabilities use their own stable prefix. Names may contain only letters, digits, `_`, and `-`; the compiler pass rejects duplicate names.

## Folder structure

- `AllowList/` — per-principal capability restrictions
- `Attribute/` — Contena MCP metadata attributes
- `Authentication/` — authentication and exception listeners
- `Command/` — `debug:mcp` command
- `Context/` — request context bridge
- `Controller/` — MCP HTTP and capability-list endpoints
- `Http/` — transport and host validation
- `Notification/` — session registry and list-changed notifications
- `Prompt/` — core prompts
- `RateLimit/` — endpoint rate-limit adapter
- `Resource/` — core resources and resource templates
- `ScheduledTask/` — abandoned session cleanup
- `Session/` — session validation and cleanup
- `Tool/` — core tools and response conventions

## Conventions

- Use the `Contena\\Core\\Framework\\Mcp` namespace and FQCN service IDs.
- Do not reintroduce legacy package metadata, `#[Package]`, App loaders, Channel API transports, or commerce-specific capability code.
- Mark implementation services `@internal` unless they are an intentional extension point. Keep supported concrete services `@final` where appropriate.
- Write tools default to `dryRun=true`; use the shared transaction helper and preserve ACL checks.
- Entity tools validate entity existence before ACL checks and serialize DAL data with `JsonEntityEncoder`.
- Entity tools that accept criteria JSON validate the built `Criteria` with `AclCriteriaValidator`; top-level `requirePrivilege()` does not cover association reads.
- Declare real prerequisites with `#[McpToolDependsOn]`; declare informational ACL requirements with `#[McpToolRequires]`. Runtime enforcement still belongs in the tool and DAL ACL layer.
- Keep tool descriptions concise and written for AI routing. Do not encode a prerequisite in prose when it should be an attribute.

## Registration and verification

Core tools need both a `mcp.tool` service tag and the MCP discovery directory configured in `Resources/config/packages/mcp.php`. Plugin or third-party Bundle tools use the `contena.mcp.tool` tag and are registered by the compiler pass.

After changing a capability, clear the cache and run:

```bash
php bin/console debug:mcp
php bin/console debug:router | rg mcp
vendor/bin/phpunit --configuration phpunit.xml.dist tests/integration/Core/Framework/Mcp
```

The HTTP discovery test is authoritative for reachability; the service-registration test catches missing DI definitions. Add focused unit coverage for each new behavior and update the Admin API schema when a route changes.

## Extension boundary

Plugins and Symfony Bundles may register generic MCP tools, prompts, and resources through DI tags and the existing attributes. They must preserve ACL enforcement, session behavior, response limits, and Contena naming. This module does not provide an App capability loader or a shopper-facing MCP endpoint.

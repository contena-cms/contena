# MCP capability registration boundary

Contena includes the upstream 6.8 App MCP capability loaders. They read typed App
features from `app_feature` and register App tools, prompts, and resources at runtime.
Keep their execution, signing, privilege, lifecycle, and telemetry behavior aligned
with the integrated upstream tree; do not replace them with a second plugin-only stack.

Generic plugin, Symfony Bundle, and App capabilities are registered through DI:

1. Tag the service with `contena.mcp.tool`, `contena.mcp.prompt`, or `contena.mcp.resource`.
2. Put the matching MCP attribute on the class.
3. Let the Contena compiler passes validate names, dependencies, groups, and privilege metadata.
4. Add focused unit coverage and an HTTP discovery assertion.

App loaders must use `AppFeatureStorage`, `AppMcpCapabilityExecutor`, and the existing
App secret/Shop ID services. Do not add commerce-specific capability loaders here.
Extension code must use the authenticated Contena context and normal ACL/DAL boundaries.

# MCP capability registration boundary

The upstream App capability loaders are intentionally not part of Contena. This directory contains only this guidance file so future contributors do not reintroduce an App/database/webhook loading stack while adding generic extensions.

Generic plugin and Symfony Bundle capabilities are registered through DI:

1. Tag the service with `contena.mcp.tool`, `contena.mcp.prompt`, or `contena.mcp.resource`.
2. Put the matching MCP attribute on the class.
3. Let the Contena compiler passes validate names, dependencies, groups, and privilege metadata.
4. Add focused unit coverage and an HTTP discovery assertion.

Do not add App manifests, App webhooks, Channel API dispatch, Channel context, or commerce-specific capability loaders here. Extension code must use the authenticated Contena context and normal ACL/DAL boundaries.

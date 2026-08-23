# MCP resources

Resources are read-only reference data identified by stable URIs. Current generic resources include:

- `contena://entities` — registered DAL entity names
- `contena://extensions` — optional local extension status metadata
- `contena://languages` — configured languages
- `contena://state-machines` — state machines and transitions
- `contena://tool-result/{id}` — session-scoped oversized tool results

Static resources return `uri`, `mimeType`, and `text` from `__invoke()`. Resource templates use `#[McpResourceTemplate]`, accept the URI placeholder and request context, and must scope reads to the current MCP session.

Register static resources with `mcp.resource` and templates with `mcp.resource_template` in `DependencyInjection/mcp.php`. Keep resources read-only, ACL-safe where data is user-specific, and independent of Channel API, Channel, Blog, Member, or other commerce models.

# MCP Server Internal Docs

Canonical admin_user-facing MCP documentation now lives in [`contena/docs` PR #2264](https://github.com/contena-cms/docs/pull/2264):

- overview, concepts, getting started, configuration, tools reference
- best practices, examples, troubleshooting
- Contena extensions
- extension guides for plugins and apps

This directory now keeps only internal material that is not part of the public docs set:

| Doc | Purpose |
|---|---|
| [Store API MCP server](store-api-mcp.md) | Internal reference for the `/store-api/_mcp` endpoint: auth, capability registration, rate limiting, sessions, limitations |
| [Spec Coverage](spec-coverage.md) | Internal protocol audit and follow-up list against the MCP spec |
| [Agent AdminUser Stories](agent-admin_user-stories.md) | Internal capability and gap tracking for core MCP workflows |
| [Blog Epic Backlog](blog-epic-backlog.md) | Planning and scope decomposition for MCP workstreams |
| [Per-admin_user MCP allowlist](gaps-admin_user-allowlist.md) | Implementation details: all auth modes, per-admin_user allowlist on admin_user entity, Copilot intersection, Admin UI |

If a topic is already covered in `contena/docs`, do not reintroduce it here unless it is repo-internal planning or audit material.

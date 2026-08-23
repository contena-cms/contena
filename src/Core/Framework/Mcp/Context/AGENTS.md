# MCP context

`McpContextProvider` reads the authenticated Contena `Context` from the current request attributes, just as Administration API controllers do. When no request context exists, it returns a CLI context so tools remain safe to invoke from diagnostics and tests.

Keep these bridges small: request and framework concerns belong here, while tools should receive the plain `Context` and enforce their own ACL requirements. `McpContextProvider` serves the Admin API and `ChannelApiMcpContextProvider` serves the Channel API; do not add commerce-specific context providers or compatibility aliases.

# MCP commands

`DebugMcpCommand` is the diagnostic surface for the same registry used by the HTTP endpoint.

## Usage

```bash
php bin/console debug:mcp
php bin/console debug:mcp contena-entity-search
php bin/console debug:mcp contena-context
php bin/console debug:mcp contena://entities
```

The command lists tools, prompts, resources, and resource templates. Tool rows include the source class, group, dependencies, and declarative ACL requirements. A capability shown here must also be reachable through the MCP registry after `Builder::build()`.

Keep this command read-only and diagnostic. Do not add domain-specific actions or duplicate MCP registration logic here. If output changes, update the command unit test and the integration discovery test.

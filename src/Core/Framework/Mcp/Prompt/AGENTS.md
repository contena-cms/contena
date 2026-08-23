# MCP prompts

Prompts provide read-only instructions that help an AI client use the generic Contena Administration MCP server. `ContenaContextPrompt` is the core prompt and should describe DAL criteria, ACL, dry-run writes, progressive discovery, response limits, and safe error recovery.

When adding a core tool, review the prompt's routing guidance for overlap with existing tools. Keep examples generic and use `contena-` capability names. Do not add App, Channel API, Channel, Blog, Member, or other commerce workflows to the core prompt.

Register prompts in `DependencyInjection/mcp.php` with the `mcp.prompt` tag and add a focused unit or discovery assertion when behavior changes.

<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;
use Contena\Core\Framework\Mcp\McpToolsetRegistry;
use Contena\Core\Framework\Mcp\Tool\ToolsetEnableTool;

/**
 * @internal
 *
 * Channel API variant of {@see ToolsetEnableTool}. A distinct concrete class is required because the
 * MCP SDK binds a tool to the class carrying #[McpTool] and the channel-api service locator keys on
 * the service id (= class). It is wired with the channel-api toolset registry, session storage and
 * the channel-api listChanged notifier, so enabling a toolset only refreshes channel-api sessions.
 */
#[McpTool(name: McpToolsetRegistry::ENABLE_TOOLSET_TOOL, title: 'Enable Toolset', description: 'Enable one MCP toolset for the current session and ask the client to refresh tools/list. The toolset remains enabled only for this MCP session.')]
#[McpToolGroup('discovery')]
class ChannelApiToolsetEnableTool extends ToolsetEnableTool
{
    #[\Override]
    public function __invoke(string $toolset): string
    {
        return parent::__invoke($toolset);
    }
}

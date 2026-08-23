<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;
use Contena\Core\Framework\Mcp\McpToolsetRegistry;
use Contena\Core\Framework\Mcp\Tool\ToolsetsListTool;

/**
 * @internal
 *
 * Channel API variant of {@see ToolsetsListTool}. A distinct concrete class is required because the
 * MCP SDK binds a tool to the class carrying #[McpTool] and the channel-api service locator keys on
 * the service id (= class). It is wired with the channel-api toolset registry + session storage.
 */
#[McpTool(name: McpToolsetRegistry::LIST_TOOLSETS_TOOL, title: 'List Toolsets', description: 'List MCP toolsets that can be enabled for the current session. Use this first for any task: no domain tools are advertised until you enable their toolset.')]
#[McpToolGroup('discovery')]
class ChannelApiToolsetsListTool extends ToolsetsListTool
{
    #[\Override]
    public function __invoke(): string
    {
        return parent::__invoke();
    }
}

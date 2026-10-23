<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;
use Contena\Core\Framework\Mcp\McpToolsetRegistry;
use Contena\Core\Framework\Mcp\Tool\ToolsetsListTool;

/**
 * @experimental stableVersion:v6.8.0
 *
 * @internal
 *
 * Store API variant of {@see ToolsetsListTool}. A distinct concrete class is required because the
 * MCP SDK binds a tool to the class carrying #[McpTool] and the store-api service locator keys on
 * the service id (= class). It is wired with the store-api toolset registry + session storage.
 */
#[McpTool(name: McpToolsetRegistry::LIST_TOOLSETS_TOOL, title: 'List Toolsets', description: 'List the MCP toolsets and whether each is enabled for the current session, either in the connect URL or with contena-toolset-enable. Use this when no advertised tool fits the task: a toolset\'s tools become available once you enable it.')]
#[McpToolGroup('discovery')]
class StoreApiToolsetsListTool extends ToolsetsListTool
{
    #[\Override]
    public function __invoke(): string
    {
        return parent::__invoke();
    }
}

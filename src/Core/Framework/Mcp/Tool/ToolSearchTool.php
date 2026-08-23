<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;

/**
 * @internal
 */
#[McpTool(name: self::NAME, title: 'Tool Search', description: 'Search the allowed Contena MCP tool catalogue by free-text query and return the most relevant tool definitions inline. Use this when a needed tool is not visible in tools/list.')]
#[McpToolGroup('discovery')]
class ToolSearchTool extends AbstractToolSearchTool
{
    /**
     * Re-declares the inherited handler on the concrete class so the MCP SDK discoverer
     * binds the tool to this class instead of the (non-instantiable) abstract base.
     */
    #[\Override]
    public function __invoke(string $query, int $maxResults = 3): string
    {
        return parent::__invoke($query, $maxResults);
    }

    #[\Override]
    protected function usageHint(): ?string
    {
        return 'A matched tool may not be advertised in tools/list yet. If your MCP client cannot call it '
            . 'directly from this result, run contena-toolsets-list to find the toolset that contains it, '
            . 'enable that toolset with contena-toolset-enable, then call the tool. Enabling a toolset emits '
            . 'a tools/listChanged notification so the client refreshes tools/list.';
    }
}

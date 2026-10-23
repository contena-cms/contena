<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpReservedToolGroupRule;

use Mcp\Capability\Attribute\McpTool;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;

#[McpTool(name: 'get_order', description: 'Read an order')]
class ExtensionToolSplitAcrossClassAndInvoke
{
    #[McpToolGroup('discovery')]
    public function __invoke(): string
    {
        return '';
    }
}

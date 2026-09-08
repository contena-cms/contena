<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpToolResponseTraitRule;

use Contena\Core\Framework\Mcp\Tool\AbstractToolSearchTool;
use Mcp\Capability\Attribute\McpTool;

#[McpTool('test-tool', 'A valid tool extending an abstract McpToolResponse')]
class ToolWithAbstractResponse extends AbstractToolSearchTool
{
}

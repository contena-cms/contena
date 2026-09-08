<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpToolResponseTraitRule;

use Contena\Core\Framework\Mcp\Tool\McpToolResponse;
use Mcp\Capability\Attribute\McpTool;

#[McpTool('test-tool', 'A valid tool extending McpToolResponse')]
class ToolWithTrait extends McpToolResponse
{
    public function __invoke(): string
    {
        return $this->success([]);
    }
}

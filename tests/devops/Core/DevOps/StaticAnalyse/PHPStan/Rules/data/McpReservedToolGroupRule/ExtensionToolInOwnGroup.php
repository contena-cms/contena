<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpReservedToolGroupRule;

use Mcp\Capability\Attribute\McpTool;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;

#[McpTool(name: 'create_cart', description: 'Create a cart')]
#[McpToolGroup('ucp')]
class ExtensionToolInOwnGroup
{
}

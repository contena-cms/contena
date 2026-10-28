<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpReservedToolGroupRule;

use Mcp\Capability\Attribute\McpTool;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;
use Contena\Core\Framework\Mcp\McpToolsetRegistry;

#[McpTool(name: McpToolsetRegistry::LIST_TOOLSETS_TOOL, description: 'List toolsets')]
#[McpToolGroup(McpToolsetRegistry::DISCOVERY_GROUP)]
class CoreMetaToolInDiscovery
{
}

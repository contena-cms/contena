<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpReservedToolGroupRule;

use Mcp\Capability\Attribute\McpTool;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;
use Contena\Core\Framework\Mcp\McpToolsetRegistry;

#[McpTool('swag-report', 'Report', 'Build a report')]
#[McpToolGroup(group: McpToolsetRegistry::DISCOVERY_GROUP)]
class ExtensionToolInDiscoveryViaConstant
{
}

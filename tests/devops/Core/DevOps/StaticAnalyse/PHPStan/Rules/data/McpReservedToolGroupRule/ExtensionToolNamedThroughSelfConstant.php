<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpReservedToolGroupRule;

use Mcp\Capability\Attribute\McpTool;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;

#[McpTool(name: self::NAME, description: 'Search the catalog')]
#[McpToolGroup(self::GROUP)]
class ExtensionToolNamedThroughSelfConstant
{
    public const NAME = 'swag-search';

    private const GROUP = 'discovery';
}

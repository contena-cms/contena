<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpReservedToolGroupRule;

use Mcp\Capability\Attribute\McpTool;
use Contena\Core\Framework\Mcp\Attribute\McpToolGroup;

class ExtensionToolInDiscoveryOnInvoke
{
    #[McpTool(name: 'lookup_catalog', description: 'Look up a blog')]
    #[McpToolGroup('discovery')]
    public function __invoke(): string
    {
        return '';
    }
}

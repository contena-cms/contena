<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD)]
final readonly class McpToolGroup
{
    public function __construct(public string $group)
    {
    }
}

<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Mcp\Xml;

/**
 * @internal
 */
interface McpCapabilityItem
{
    public function getName(): string;

    /**
     * @return array<string, mixed>
     */
    public function toArray(string $defaultLocale): array;
}

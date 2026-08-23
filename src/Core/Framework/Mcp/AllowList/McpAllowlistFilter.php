<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\AllowList;

/**
 * Pure allowlist-filtering logic for MCP tool, resource, and prompt calls and list responses.
 * Contains no HTTP or JSON concerns — operates on decoded data structures only.
 */
class McpAllowlistFilter
{
    /**
     * Returns true when a tools/call for $toolName should be rejected.
     *
     * @param list<string> $allowlist
     */
    public function isToolCallDenied(string $toolName, array $allowlist): bool
    {
        return !\in_array($toolName, $allowlist, true);
    }

    /**
     * Returns true when a resources/read for $resourceUri should be rejected.
     *
     * contena://tool-result/ URIs are always allowed — they are session-scoped
     * internal resources produced by tool calls, not plugin-registered resources.
     *
     * @param list<string> $allowlist
     */
    public function isResourceReadDenied(string $resourceUri, array $allowlist): bool
    {
        if (str_starts_with($resourceUri, 'contena://tool-result/')) {
            return false;
        }

        return !\in_array($resourceUri, $allowlist, true);
    }

    /**
     * Returns true when a prompts/get for $promptName should be rejected.
     *
     * @param list<string> $allowlist
     */
    public function isPromptGetDenied(string $promptName, array $allowlist): bool
    {
        return !\in_array($promptName, $allowlist, true);
    }
}

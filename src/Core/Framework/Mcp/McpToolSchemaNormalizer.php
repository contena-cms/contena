<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp;

/**
 * @internal
 *
 * Forces empty JSON Schema `properties` maps in MCP tool definitions to serialize as `{}` rather
 * than `[]`. The PHP MCP SDK can lose the object type after decoding a schema into an associative
 * array, which makes strict clients reject the complete tool list.
 */
final class McpToolSchemaNormalizer
{
    /**
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>|null
     */
    public static function normalizeToolListResult(array $message): ?array
    {
        if (!\is_array($message['result'] ?? null) || !\is_array($message['result']['tools'] ?? null)) {
            return null;
        }

        $changed = false;

        foreach ($message['result']['tools'] as $index => $tool) {
            if (!\is_array($tool)) {
                continue;
            }

            $message['result']['tools'][$index] = self::normalizeToolInto($tool, $changed);
        }

        return $changed ? $message : null;
    }

    /**
     * @param array<string, mixed> $tool
     *
     * @return array<string, mixed>
     */
    public static function normalizeTool(array $tool): array
    {
        $changed = false;

        return self::normalizeToolInto($tool, $changed);
    }

    /**
     * @param array<string, mixed> $tool
     *
     * @return array<string, mixed>
     */
    private static function normalizeToolInto(array $tool, bool &$changed): array
    {
        foreach (['inputSchema', 'outputSchema'] as $schemaKey) {
            if (\is_array($tool[$schemaKey] ?? null)) {
                $tool[$schemaKey] = self::normalizeSchemaNode($tool[$schemaKey], $changed);
            }
        }

        return $tool;
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>
     */
    private static function normalizeSchemaNode(array $node, bool &$changed): array
    {
        foreach ($node as $key => $value) {
            if ($key === 'properties' && $value === []) {
                $node[$key] = new \stdClass();
                $changed = true;

                continue;
            }

            if (\is_array($value)) {
                $node[$key] = self::normalizeSchemaNode($value, $changed);
            }
        }

        return $node;
    }
}

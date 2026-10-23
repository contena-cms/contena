<?php declare(strict_types=1);

namespace Contena\Core\Framework\Mcp\Result;


/**
 * @experimental stableVersion:v6.8.0
 *
 * A signed reference to a stored tool result, as returned by
 * {@see \Contena\Core\Framework\Mcp\ToolResultCacheStorage::storeFor()}.
 */
final readonly class McpToolResultPointer
{
    public const URI_PREFIX = 'contena://tool-result/';

    public function __construct(
        public string $token,
        public \DateTimeImmutable $expiresAt,
    ) {
    }

    public function uri(): string
    {
        return self::URI_PREFIX . $this->token;
    }
}

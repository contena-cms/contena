<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\AllowList;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\AllowList\McpAllowlistFilter;

/**
 * @internal
 */
#[CoversClass(McpAllowlistFilter::class)]
class McpAllowlistFilterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function toolCallProvider(): iterable
    {
        yield 'listed tool is allowed' => ['contena-entity-search', ['contena-entity-search'], false];
        yield 'unlisted tool is denied' => ['contena-entity-delete', ['contena-entity-search'], true];
        yield 'empty list denies every tool' => ['contena-entity-search', [], true];
    }

    /**
     * @param list<string> $allowlist
     */
    #[DataProvider('toolCallProvider')]
    public function testToolCalls(string $tool, array $allowlist, bool $denied): void
    {
        static::assertSame($denied, new McpAllowlistFilter()->isToolCallDenied($tool, $allowlist));
    }

    public function testToolResultResourceRemainsReadable(): void
    {
        static::assertFalse(new McpAllowlistFilter()->isResourceReadDenied('contena://tool-result/result-id', []));
    }

    public function testUnlistedPromptIsDenied(): void
    {
        static::assertTrue(new McpAllowlistFilter()->isPromptGetDenied('other-prompt', ['contena-context']));
    }
}

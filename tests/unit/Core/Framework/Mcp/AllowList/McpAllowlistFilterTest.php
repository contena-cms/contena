<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\AllowList;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Mcp\AllowList\McpAllowlistFilter;

/**
 * @internal
 */
#[CoversClass(McpAllowlistFilter::class)]
class McpAllowlistFilterTest extends TestCase
{
    private McpAllowlistFilter $filter;

    protected function setUp(): void
    {
        $this->filter = new McpAllowlistFilter();
    }

    // ── Tools ────────────────────────────────────────────────────────────────

    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function toolCallDeniedProvider(): iterable
    {
        yield 'tool in allowlist is not denied' => ['contena-entity-search', ['contena-entity-search', 'contena-entity-read'], false];
        yield 'tool not in allowlist is denied' => ['contena-entity-delete', ['contena-entity-search'], true];
        yield 'empty allowlist denies everything' => ['any-tool', [], true];
        yield 'exact name match required' => ['contena-entity', ['contena-entity-search'], true];
    }

    /**
     * @param list<string> $allowlist
     */
    #[DataProvider('toolCallDeniedProvider')]
    public function testIsToolCallDenied(string $toolName, array $allowlist, bool $expectedDenied): void
    {
        static::assertSame($expectedDenied, $this->filter->isToolCallDenied($toolName, $allowlist));
    }

    // ── Resources ────────────────────────────────────────────────────────────

    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function resourceReadDeniedProvider(): iterable
    {
        yield 'resource in allowlist is not denied' => ['contena://entities', ['contena://entities', 'contena://currencies'], false];
        yield 'resource not in allowlist is denied' => ['contena://state-machines', ['contena://entities'], true];
        yield 'empty allowlist denies everything' => ['contena://entities', [], true];
        yield 'tool-result URI is never denied even with empty allowlist' => ['contena://tool-result/abc123', [], false];
        yield 'tool-result URI is never denied when not in allowlist' => ['contena://tool-result/xyz', ['contena://entities'], false];
    }

    /**
     * @param list<string> $allowlist
     */
    #[DataProvider('resourceReadDeniedProvider')]
    public function testIsResourceReadDenied(string $uri, array $allowlist, bool $expectedDenied): void
    {
        static::assertSame($expectedDenied, $this->filter->isResourceReadDenied($uri, $allowlist));
    }

    // ── Prompts ──────────────────────────────────────────────────────────────

    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function promptGetDeniedProvider(): iterable
    {
        yield 'prompt in allowlist is not denied' => ['contena-context', ['contena-context'], false];
        yield 'prompt not in allowlist is denied' => ['other-prompt', ['contena-context'], true];
        yield 'empty allowlist denies everything' => ['contena-context', [], true];
    }

    /**
     * @param list<string> $allowlist
     */
    #[DataProvider('promptGetDeniedProvider')]
    public function testIsPromptGetDenied(string $promptName, array $allowlist, bool $expectedDenied): void
    {
        static::assertSame($expectedDenied, $this->filter->isPromptGetDenied($promptName, $allowlist));
    }
}

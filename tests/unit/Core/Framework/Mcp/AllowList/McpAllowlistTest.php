<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\AllowList;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\AllowList\McpAllowlist;

/**
 * @internal
 */
#[CoversClass(McpAllowlist::class)]
class McpAllowlistTest extends TestCase
{
    public function testUnrestrictedReturnsAllNull(): void
    {
        $allowlist = McpAllowlist::unrestricted();

        static::assertNull($allowlist->tools);
        static::assertNull($allowlist->resources);
        static::assertNull($allowlist->prompts);
    }

    public function testFromJsonNullReturnsUnrestricted(): void
    {
        $allowlist = McpAllowlist::fromJson(null);

        static::assertNull($allowlist->tools);
        static::assertNull($allowlist->resources);
        static::assertNull($allowlist->prompts);
    }

    public function testFromJsonEmptyStringReturnsUnrestricted(): void
    {
        $allowlist = McpAllowlist::fromJson('');

        static::assertNull($allowlist->tools);
        static::assertNull($allowlist->resources);
        static::assertNull($allowlist->prompts);
    }

    public function testFromJsonInvalidJsonReturnsUnrestricted(): void
    {
        $allowlist = McpAllowlist::fromJson('not-valid-json');

        static::assertNull($allowlist->tools);
        static::assertNull($allowlist->resources);
        static::assertNull($allowlist->prompts);
    }

    public function testFromJsonScalarReturnsUnrestricted(): void
    {
        $allowlist = McpAllowlist::fromJson('"string"');

        static::assertNull($allowlist->tools);
        static::assertNull($allowlist->resources);
        static::assertNull($allowlist->prompts);
    }

    public function testFromJsonParsesToolsList(): void
    {
        $allowlist = McpAllowlist::fromJson('{"tools":["tool-a","tool-b"]}');

        static::assertSame(['tool-a', 'tool-b'], $allowlist->tools);
        static::assertNull($allowlist->resources);
        static::assertNull($allowlist->prompts);
    }

    public function testFromJsonParsesAllTypes(): void
    {
        $json = json_encode([
            'tools' => ['contena-entity-read', 'contena-entity-search'],
            'resources' => ['contena://entities'],
            'prompts' => ['contena-context'],
        ]);
        static::assertNotFalse($json);

        $allowlist = McpAllowlist::fromJson($json);

        static::assertSame(['contena-entity-read', 'contena-entity-search'], $allowlist->tools);
        static::assertSame(['contena://entities'], $allowlist->resources);
        static::assertSame(['contena-context'], $allowlist->prompts);
    }

    public function testFromJsonNullKeyMeansUnrestricted(): void
    {
        $allowlist = McpAllowlist::fromJson('{"tools":null,"resources":["contena://entities"]}');

        static::assertNull($allowlist->tools);
        static::assertSame(['contena://entities'], $allowlist->resources);
        static::assertNull($allowlist->prompts);
    }

    public function testFromJsonEmptyArrayBlocksEverything(): void
    {
        $allowlist = McpAllowlist::fromJson('{"tools":[],"resources":[],"prompts":[]}');

        static::assertSame([], $allowlist->tools);
        static::assertSame([], $allowlist->resources);
        static::assertSame([], $allowlist->prompts);
    }

    public function testFromJsonFiltersNonStringValues(): void
    {
        $allowlist = McpAllowlist::fromJson('{"tools":["valid-tool",123,null,"another-tool"]}');

        static::assertSame(['valid-tool', 'another-tool'], $allowlist->tools);
    }

    public function testFromJsonInvalidTypeForListReturnsNull(): void
    {
        $allowlist = McpAllowlist::fromJson('{"tools":"not-an-array"}');

        static::assertNull($allowlist->tools);
    }

    public function testFromJsonAbsentKeyReturnsNull(): void
    {
        $allowlist = McpAllowlist::fromJson('{}');

        static::assertNull($allowlist->tools);
        static::assertNull($allowlist->resources);
        static::assertNull($allowlist->prompts);
    }
}

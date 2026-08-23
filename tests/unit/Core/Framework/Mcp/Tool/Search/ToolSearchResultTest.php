<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Tool\Search;

use Mcp\Schema\Tool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\Tool\Search\ToolSearchResult;

/**
 * @internal
 */
#[CoversClass(ToolSearchResult::class)]
class ToolSearchResultTest extends TestCase
{
    public function testStoresSearchResultData(): void
    {
        $tool = new Tool(
            name: 'contena-blog-search',
            title: null,
            inputSchema: ['type' => 'object', 'properties' => [], 'required' => []],
            description: 'Search blogs',
            annotations: null,
        );

        $result = new ToolSearchResult($tool, 3.5, ['name:substring']);

        static::assertSame($tool, $result->tool);
        static::assertSame(3.5, $result->score);
        static::assertSame(['name:substring'], $result->matchedIn);
    }
}

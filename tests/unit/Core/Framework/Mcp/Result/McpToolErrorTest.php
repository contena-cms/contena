<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Result;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\Result\McpToolError;

/**
 * @internal
 */
#[CoversClass(McpToolError::class)]
class McpToolErrorTest extends TestCase
{
    public function testDefaultsToTheGenericCodeWithoutDetails(): void
    {
        $error = new McpToolError('Something failed');

        static::assertSame('Something failed', $error->message);
        static::assertSame(McpToolError::TOOL_ERROR, $error->code);
        static::assertSame([], $error->details);
    }

    public function testKeepsCodeAndDetails(): void
    {
        $error = new McpToolError('Missing privilege: blog:read', McpToolError::MISSING_PRIVILEGE, ['privileges' => ['blog:read']]);

        static::assertSame(McpToolError::MISSING_PRIVILEGE, $error->code);
        static::assertSame(['privileges' => ['blog:read']], $error->details);
    }
}

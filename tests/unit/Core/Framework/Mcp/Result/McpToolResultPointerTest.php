<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Result;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\Result\McpToolResultPointer;

/**
 * @internal
 */
#[CoversClass(McpToolResultPointer::class)]
class McpToolResultPointerTest extends TestCase
{
    public function testTheUriIsTheToolResultResourceWithTheToken(): void
    {
        $pointer = new McpToolResultPointer('abc.1790590344.signature', new \DateTimeImmutable('2026-09-28T11:00:00+00:00'));

        static::assertSame('contena://tool-result/abc.1790590344.signature', $pointer->uri());
        static::assertStringStartsWith(McpToolResultPointer::URI_PREFIX, $pointer->uri());
    }

    public function testKeepsTheExpiry(): void
    {
        $expiresAt = new \DateTimeImmutable('2026-09-28T11:00:00+00:00');

        static::assertSame($expiresAt, (new McpToolResultPointer('abc', $expiresAt))->expiresAt);
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Page\Robots\Parser;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Page\Robots\Parser\ParsedRobots;
use Contena\Storefront\Page\Robots\Parser\ParseIssueSeverity;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ParsedRobots::class)]
class ParsedRobotsTest extends TestCase
{
    public function testHasUserAgentBlocks(): void
    {
        // Empty parsed result has no user agent blocks
        $emptyParsed = new ParsedRobots([], [], []);
        static::assertFalse($emptyParsed->hasUserAgentBlocks());

        // Parsed result with user agent blocks
        $userAgentBlocks = [
        ];
        $parsedWithBlocks = new ParsedRobots($userAgentBlocks, [], []);
        static::assertTrue($parsedWithBlocks->hasUserAgentBlocks());
    }

    public function testIssueFiltering(): void
    {
        // Test with no issues
        $emptyParsed = new ParsedRobots([], [], []);
        static::assertFalse($emptyParsed->hasErrors());
        static::assertFalse($emptyParsed->hasWarnings());
        static::assertCount(0, $emptyParsed->getErrors());
        static::assertCount(0, $emptyParsed->getWarnings());

        // Test with mixed errors and warnings
        $issues = [
        ];

        $parsed = new ParsedRobots([], [], $issues);

        // Test has* methods
        static::assertTrue($parsed->hasErrors());
        static::assertTrue($parsed->hasWarnings());

        // Test get* methods filter correctly
        $errors = $parsed->getErrors();
        $warnings = $parsed->getWarnings();

        static::assertCount(2, $errors);
        static::assertCount(2, $warnings);

        // Verify returned arrays are lists (sequential keys)
        static::assertArrayHasKey(0, $errors);
        static::assertArrayHasKey(1, $errors);
        static::assertArrayHasKey(0, $warnings);
        static::assertArrayHasKey(1, $warnings);

        // Verify correct severity filtering
        static::assertSame(ParseIssueSeverity::ERROR, $errors[0]->severity);
        static::assertSame(ParseIssueSeverity::ERROR, $errors[1]->severity);
        static::assertSame(ParseIssueSeverity::WARNING, $warnings[0]->severity);
        static::assertSame(ParseIssueSeverity::WARNING, $warnings[1]->severity);
    }
}

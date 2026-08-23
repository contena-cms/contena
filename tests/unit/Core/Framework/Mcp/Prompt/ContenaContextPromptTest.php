<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Prompt;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\Prompt\ContenaContextPrompt;

/**
 * @internal
 */
#[CoversClass(ContenaContextPrompt::class)]
class ContenaContextPromptTest extends TestCase
{
    public function testInvokeReturnsMessagesWithRoleAndContent(): void
    {
        $prompt = new ContenaContextPrompt();
        $result = ($prompt)();

        static::assertIsArray($result);
        static::assertNotEmpty($result);
        static::assertArrayHasKey('role', $result[0]);
        static::assertArrayHasKey('content', $result[0]);
        static::assertSame('user', $result[0]['role']);
    }

    public function testContentContainsKeyPhrases(): void
    {
        $prompt = new ContenaContextPrompt();
        $result = ($prompt)();

        $content = $result[0]['content'];
        static::assertStringContainsString('Contena', $content);
        static::assertStringContainsString('entity', $content);
        static::assertStringContainsString('contena-tool-search', $content);
        static::assertStringContainsString('contena-toolsets-list', $content);
        static::assertStringContainsString('contena-toolset-enable', $content);
        static::assertStringContainsString('contena-theme-config', $content);
        static::assertStringContainsString('UUID or name', $content);
        static::assertStringContainsString('contena://channels', $content);
        static::assertStringContainsString('ACL privileges', $content);
    }
}

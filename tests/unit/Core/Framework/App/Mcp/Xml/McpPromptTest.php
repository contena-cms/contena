<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\App\Mcp\Xml;

use Contena\Core\Framework\App\Mcp\Mcp;
use Contena\Core\Framework\App\Mcp\Xml\McpPrompt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(McpPrompt::class)]
class McpPromptTest extends TestCase
{
    public function testFromXmlParsesAllFields(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/../_fixtures/mcp.xml');

        $prompts = $mcp->getPrompts();
        static::assertNotNull($prompts);

        $prompt = $prompts->getPrompts()[0];
        static::assertSame('blog-context', $prompt->getName());
        static::assertSame('https://app.example.com/mcp/prompt/blog-context', $prompt->getUrl());
        static::assertSame([
            'en-GB' => 'Blog context',
            'zh-CN' => '文章上下文',
        ], $prompt->getLabel());
        static::assertSame([
            'en-GB' => 'Context for blog management',
            'zh-CN' => '文章管理上下文',
        ], $prompt->getDescription());
    }

    public function testPromptWithoutDescriptionReturnsEmptyArray(): void
    {
        $mcp = Mcp::createFromXmlFile(__DIR__ . '/../_fixtures/mcp.xml');

        $prompts = $mcp->getPrompts();
        static::assertNotNull($prompts);

        $prompt = $prompts->getPrompts()[1];
        static::assertSame('category-context', $prompt->getName());
        static::assertSame([], $prompt->getDescription());
    }

    public function testToArrayContainsTranslations(): void
    {
        $prompt = McpPrompt::fromArray([
            'name' => 'my-prompt',
            'url' => 'https://example.com/mcp/prompt',
            'label' => ['en-GB' => 'My Prompt', 'de-DE' => 'Mein Prompt'],
            'description' => ['en-GB' => 'Desc'],
        ]);

        $data = $prompt->toArray('en-GB');

        static::assertSame('my-prompt', $data['name']);
        static::assertSame('https://example.com/mcp/prompt', $data['url']);
        static::assertSame('My Prompt', $data['label']['en-GB']);
        static::assertSame('Mein Prompt', $data['label']['de-DE']);
        static::assertSame('Desc', $data['description']['en-GB']);
    }

    public function testFromArraySetsProperties(): void
    {
        $prompt = McpPrompt::fromArray([
            'name' => 'test',
            'url' => 'https://test.example.com/prompt',
            'label' => ['en-GB' => 'Test'],
            'description' => [],
        ]);

        static::assertSame('test', $prompt->getName());
        static::assertSame('https://test.example.com/prompt', $prompt->getUrl());
        static::assertSame(['en-GB' => 'Test'], $prompt->getLabel());
        static::assertSame([], $prompt->getDescription());
    }
}

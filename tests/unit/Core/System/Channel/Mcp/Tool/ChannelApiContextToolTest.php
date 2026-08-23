<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Mcp\Context\ChannelApiMcpContextProvider;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Mcp\Tool\ChannelApiContextTool;
use Contena\Core\System\Member\MemberEntity;

/**
 * @internal
 */
#[CoversClass(ChannelApiContextTool::class)]
class ChannelApiContextToolTest extends TestCase
{
    public function testReturnsCurrentChannelApiContext(): void
    {
        $member = new MemberEntity();
        $member->setId('member-id');

        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getChannelId')->willReturn('channel-id');
        $channelContext->method('getToken')->willReturn('context-token');
        $channelContext->method('getLanguageId')->willReturn('language-id');
        $channelContext->method('getMember')->willReturn($member);

        $contextProvider = static::createStub(ChannelApiMcpContextProvider::class);
        $contextProvider->method('getChannelContext')->willReturn($channelContext);

        $tool = new ChannelApiContextTool($contextProvider);
        $data = json_decode($tool(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame('channel-id', $data['data']['channelId']);
        static::assertSame('context-token', $data['data']['token']);
        static::assertSame('language-id', $data['data']['languageId']);
        static::assertTrue($data['data']['memberAuthenticated']);
        static::assertSame('member-id', $data['data']['memberId']);
    }

    public function testReturnsErrorWithoutChannelApiContext(): void
    {
        $contextProvider = static::createStub(ChannelApiMcpContextProvider::class);
        $contextProvider->method('getChannelContext')->willReturn(null);

        $tool = new ChannelApiContextTool($contextProvider);
        $data = json_decode($tool(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('No Channel API context', $data['error']);
    }
}

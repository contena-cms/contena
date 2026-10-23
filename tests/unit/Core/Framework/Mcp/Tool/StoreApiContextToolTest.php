<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Mcp\Context\StoreApiMcpContextProvider;
use Contena\Core\System\Channel\Mcp\Tool\StoreApiContextTool;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
#[CoversClass(StoreApiContextTool::class)]
class StoreApiContextToolTest extends TestCase
{
    public function testReturnsCurrentStoreApiContext(): void
    {
        $user = new UserEntity();
        $user->setId('user-id');

        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getChannelId')->willReturn('sales-channel-id');
        $channelContext->method('getToken')->willReturn('context-token');
        $channelContext->method('getLanguageId')->willReturn('language-id');
        $channelContext->method('getCurrencyId')->willReturn('currency-id');
        $channelContext->method('getUser')->willReturn($user);

        $contextProvider = static::createStub(StoreApiMcpContextProvider::class);
        $contextProvider->method('getChannelContext')->willReturn($channelContext);

        $tool = new StoreApiContextTool($contextProvider);
        $data = json_decode($tool(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
        static::assertSame('sales-channel-id', $data['data']['channelId']);
        static::assertSame('context-token', $data['data']['token']);
        static::assertSame('language-id', $data['data']['languageId']);
        static::assertSame('currency-id', $data['data']['currencyId']);
        static::assertTrue($data['data']['userAuthenticated']);
        static::assertSame('user-id', $data['data']['userId']);
    }

    public function testReturnsErrorWithoutStoreApiContext(): void
    {
        $contextProvider = static::createStub(StoreApiMcpContextProvider::class);
        $contextProvider->method('getChannelContext')->willReturn(null);

        $tool = new StoreApiContextTool($contextProvider);
        $data = json_decode($tool(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('No Store API sales-channel context', $data['error']);
    }
}

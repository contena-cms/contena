<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Channel\Context\CachedChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\Test\Generator;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @internal
 */
#[CoversClass(CachedChannelContextFactory::class)]
class CachedChannelContextFactoryTest extends TestCase
{
    public function testMemberSpecificOptionsAreNotCached(): void
    {
        $context = Generator::generateChannelContext();
        $options = [ChannelContextService::MEMBER_ID => 'member-id'];

        $inner = $this->createMock(ChannelContextFactory::class);
        $inner->expects($this->once())
            ->method('create')
            ->with('token', 'channel-id', $options)
            ->willReturn($context);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('get');

        $factory = new CachedChannelContextFactory($inner, $cache);

        static::assertSame($context, $factory->create('token', 'channel-id', $options));
    }

    public function testFreshlyBuiltContextIsReturnedDirectly(): void
    {
        $context = Generator::generateChannelContext();
        $options = [ChannelContextService::LANGUAGE_ID => 'language-id'];

        $inner = $this->createMock(ChannelContextFactory::class);
        $inner->expects($this->once())
            ->method('create')
            ->with('token', 'channel-id', $options)
            ->willReturn($context);

        $storedValue = null;
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->exactly(2))
            ->method('get')
            ->willReturnCallback(static function (string $key, callable $callback) use (&$storedValue) {
                // the second call replays the stored payload like a warm cache pool would
                return $storedValue ??= $callback(static::createStub(ItemInterface::class));
            });

        $factory = new CachedChannelContextFactory($inner, $cache);

        $first = $factory->create('token', 'channel-id', $options);

        static::assertSame($context, $first, 'a context built in this call is returned without a serialization round trip');

        $second = $factory->create('other-token', 'channel-id', $options);

        static::assertNotSame($context, $second, 'a cache hit is unserialized into a fresh instance');
        static::assertSame('other-token', $second->getToken());
        static::assertSame($context->getChannelId(), $second->getChannelId());
    }
}

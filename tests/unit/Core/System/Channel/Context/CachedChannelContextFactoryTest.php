<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Channel\Context\CachedChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\InvalidationRaceAwareCache;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\Test\Generator;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

/**
 * @internal
 */
#[CoversClass(CachedChannelContextFactory::class)]
class CachedChannelContextFactoryTest extends TestCase
{
    public function testUserSpecificOptionsAreNotCached(): void
    {
        $context = Generator::generateChannelContext();
        $options = [ChannelContextService::CUSTOMER_ID => 'user-id'];

        $inner = $this->createMock(ChannelContextFactory::class);
        $inner->expects($this->once())
            ->method('create')
            ->with('token', 'sales-channel-id', $options)
            ->willReturn($context);

        $factory = new CachedChannelContextFactory(
            $inner,
            new InvalidationRaceAwareCache(new TagAwareAdapter(new ArrayAdapter())),
        );

        static::assertSame($context, $factory->create('token', 'sales-channel-id', $options));
    }

    #[DataProvider('injectedAddressOptionProvider')]
    public function testContextWithInjectedAddressIsNotCached(string $option): void
    {
        $context = Generator::generateChannelContext();
        $options = [$option => new UserAddressEntity()];

        $inner = $this->createMock(ChannelContextFactory::class);
        $inner->expects($this->exactly(2))
            ->method('create')
            ->with('token', 'sales-channel-id', $options)
            ->willReturn($context);

        $factory = new CachedChannelContextFactory(
            $inner,
            new InvalidationRaceAwareCache(new TagAwareAdapter(new ArrayAdapter())),
        );

        $factory->create('token', 'sales-channel-id', $options);
        $factory->create('token', 'sales-channel-id', $options);
    }

    public static function injectedAddressOptionProvider(): \Generator
    {
        yield 'injected billing address' => [ChannelContextService::BILLING_ADDRESS];
        yield 'injected shipping address' => [ChannelContextService::SHIPPING_ADDRESS];
    }

    public function testFreshlyBuiltContextIsReturnedDirectly(): void
    {
        $context = Generator::generateChannelContext();
        $options = [ChannelContextService::LANGUAGE_ID => 'language-id'];

        $inner = $this->createMock(ChannelContextFactory::class);
        $inner->expects($this->once())
            ->method('create')
            ->with('token', 'sales-channel-id', $options)
            ->willReturn($context);

        $cache = new InvalidationRaceAwareCache(new TagAwareAdapter(new ArrayAdapter()));

        $factory = new CachedChannelContextFactory($inner, $cache);

        $first = $factory->create('token', 'sales-channel-id', $options);

        static::assertSame($context, $first, 'a context built in this call is returned without a serialization round trip');

        $second = $factory->create('other-token', 'sales-channel-id', $options);

        static::assertNotSame($context, $second, 'a cache hit is unserialized into a fresh instance');
        static::assertSame('other-token', $second->getToken());
        static::assertSame($context->getChannelId(), $second->getChannelId());
    }

    public function testDoesNotCacheContextWhenTheMarkerWasInvalidatedDuringCreation(): void
    {
        $firstContext = Generator::generateChannelContext();
        $secondContext = Generator::generateChannelContext();
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $calls = 0;
        $inner = $this->createMock(ChannelContextFactory::class);
        $inner->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function () use ($firstContext, $secondContext, $cache, &$calls) {
                if ($calls++ === 0) {
                    $cache->invalidateTags([CachedChannelContextFactory::ALL_TAG]);

                    return $firstContext;
                }

                return $secondContext;
            });

        $factory = new CachedChannelContextFactory($inner, new InvalidationRaceAwareCache($cache));

        static::assertSame($firstContext, $factory->create('token', 'sales-channel-id'));
        static::assertSame($secondContext, $factory->create('another-token', 'sales-channel-id'));
    }
}

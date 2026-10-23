<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\SystemConfig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\AbstractSystemConfigLoader;
use Contena\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Contena\Core\System\SystemConfig\MemoizedSystemConfigLoader;
use Contena\Core\System\SystemConfig\Store\MemoizedSystemConfigStore;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(MemoizedSystemConfigLoader::class)]
class MemoizedSystemConfigLoaderTest extends TestCase
{
    public function testMemoizationWithChannelIdWorks(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects($this->once())
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(TestDefaults::CHANNEL);
        static::assertSame($expectedConfig, $config);

        // Ensure a second call does not call the load method and returns the same result.
        $config = $service->load(TestDefaults::CHANNEL);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationWithoutChannelIdWorks(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects($this->once())
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(null);
        static::assertSame($expectedConfig, $config);

        // Ensure a second call does not call the load method and returns the same result.
        $config = $service->load(null);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationResetsForChannelId(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects($this->exactly(2))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(TestDefaults::CHANNEL);
        static::assertSame($expectedConfig, $config);

        // Reset config without sales channel ID.
        $configStore->removeConfig(TestDefaults::CHANNEL);

        // The load method is now called a second time as memoization has been reset for the sales channel.
        $config = $service->load(TestDefaults::CHANNEL);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationResetsWithoutChannelId(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects($this->exactly(2))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(null);
        static::assertSame($expectedConfig, $config);

        // Reset config without sales channel ID.
        $configStore->removeConfig(null);

        // The load method is now called a second time as the global memoization has been reset.
        $config = $service->load(null);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationResetWithoutChannelIdForAllChannels(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects($this->exactly(2))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(TestDefaults::CHANNEL);
        static::assertSame($expectedConfig, $config);

        // AS the global config is reset, all sales channels are reset and load is called a second time.
        $configStore->removeConfig(null);

        // The load method is now called a second time as the memoization has been reset for all sales channels.
        $config = $service->load(TestDefaults::CHANNEL);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationDoesNotResetOnResetForDifferentChannelId(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects($this->exactly(1))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(TestDefaults::CHANNEL);
        static::assertSame($expectedConfig, $config);

        // Reset the config for a different sales channel ID.
        $configStore->removeConfig(Uuid::randomHex());

        // The load method is not called again as the config was reset for a different sales channel ID.
        $config = $service->load(TestDefaults::CHANNEL);
        static::assertSame($expectedConfig, $config);
    }

    public function testUsingDifferentChannelIdsCallsLoad(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects($this->exactly(2))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(TestDefaults::CHANNEL);
        static::assertSame($expectedConfig, $config);

        // Ensure that using a different sales channel ID calls the load method again.
        $config = $service->load(Uuid::randomHex());
        static::assertSame($expectedConfig, $config);
    }

    public function testUsingGlobalAndChannelIdLoadCallsLoad(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects($this->exactly(2))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(null);
        static::assertSame($expectedConfig, $config);

        // Ensure that using a sales channel ID calls the load method again.
        $config = $service->load(TestDefaults::CHANNEL);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationResetsOnValueChangeEventForChannelId(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects($this->exactly(2))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(TestDefaults::CHANNEL);
        static::assertSame($expectedConfig, $config);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($configStore);

        // Dispatching the event resets the memoization and ensures load is called a second time.
        $dispatcher->dispatch(new SystemConfigChangedEvent('abc.config.foo', 'none', TestDefaults::CHANNEL));

        // The load method is now called a second time as memoization has been reset for the sales channel.
        $config = $service->load(TestDefaults::CHANNEL);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationResetsOnValueChangeEventWithoutChannelId(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects($this->exactly(2))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(null);
        static::assertSame($expectedConfig, $config);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($configStore);

        // Dispatching the event resets the memoization and ensures load is called a second time.
        $dispatcher->dispatch(new SystemConfigChangedEvent('abc.config.foo', 'none', null));

        // The load method is now called a second time as the global memoization has been reset.
        $config = $service->load(null);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationResetsOnValueChangeEventWithoutChannelIdForAllChannels(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects($this->exactly(2))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(TestDefaults::CHANNEL);
        static::assertSame($expectedConfig, $config);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($configStore);

        // Dispatching the event resets the memoization for all sales channels and ensures load is called a second time.
        $dispatcher->dispatch(new SystemConfigChangedEvent('abc.config.foo', 'none', null));

        // The load method is now called a second time as the memoization has been reset for all sales channels.
        $config = $service->load(TestDefaults::CHANNEL);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationDoesNotResetOnValueChangedEventForDifferentChannelId(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects($this->exactly(1))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(TestDefaults::CHANNEL);
        static::assertSame($expectedConfig, $config);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($configStore);

        // Dispatching the event resets the memoization for a different sales channel ID.
        $dispatcher->dispatch(new SystemConfigChangedEvent('abc.config.foo', 'none', Uuid::randomHex()));

        // The load method is not called again as the config was reset for a different sales channel ID.
        $config = $service->load(TestDefaults::CHANNEL);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationResetsWhenCallingMethod(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects($this->exactly(2))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);

        $config = $service->load(TestDefaults::CHANNEL);
        static::assertSame($expectedConfig, $config);

        $configStore->reset();

        // The load method is now called a second time as memoization has been reset.
        $config = $service->load(TestDefaults::CHANNEL);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationResetsOnValueChangeEventWithDecoratedSystemConfigLoader(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects($this->exactly(2))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);
        $decorated = new DecoratedMemoizedResetTestSystemConfigLoader($service);

        $config = $decorated->load(TestDefaults::CHANNEL);
        static::assertSame($expectedConfig, $config);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($configStore);

        // Dispatching the event resets the memoization and ensures load is called a second time.
        $dispatcher->dispatch(new SystemConfigChangedEvent('abc.config.foo', 'none', TestDefaults::CHANNEL));

        // The load method is now called a second time as memoization has been reset for the sales channel.
        $config = $decorated->load(TestDefaults::CHANNEL);
        static::assertSame($expectedConfig, $config);
    }

    public function testMemoizationResetsWhenCallingMethodWithDecoratedSystemConfigLoader(): void
    {
        $expectedConfig = ['abc' => ['config' => ['foo' => 'abc']]];

        $mock = $this->createMock(AbstractSystemConfigLoader::class);
        $mock->expects($this->exactly(2))
            ->method('load')
            ->willReturn($expectedConfig);

        $configStore = new MemoizedSystemConfigStore();
        $service = new MemoizedSystemConfigLoader($mock, $configStore);
        $decorated = new DecoratedMemoizedResetTestSystemConfigLoader($service);

        $config = $decorated->load(TestDefaults::CHANNEL);
        static::assertSame($expectedConfig, $config);

        $configStore->reset();

        // The load method is now called a second time as memoization has been reset.
        $config = $decorated->load(TestDefaults::CHANNEL);
        static::assertSame($expectedConfig, $config);
    }
}

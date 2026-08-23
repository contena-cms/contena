<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\SystemConfig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\AbstractSystemConfigLoader;
use Contena\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Contena\Core\System\SystemConfig\MemoizedSystemConfigLoader;
use Contena\Core\System\SystemConfig\Store\MemoizedSystemConfigStore;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(MemoizedSystemConfigLoader::class)]
class MemoizedSystemConfigLoaderTest extends TestCase
{
    public function testMemoizesConfiguration(): void
    {
        $expectedConfig = ['example' => ['enabled' => true]];

        $loader = $this->createMock(AbstractSystemConfigLoader::class);
        $loader->expects($this->once())->method('load')->with(null, null)->willReturn($expectedConfig);

        $service = new MemoizedSystemConfigLoader($loader, new MemoizedSystemConfigStore());

        static::assertSame($expectedConfig, $service->load(null));
        static::assertSame($expectedConfig, $service->load(null));
    }

    public function testChangeEventResetsMemoizedConfigurationThroughDecoratedLoader(): void
    {
        $expectedConfig = ['example' => ['enabled' => true]];

        $loader = $this->createMock(AbstractSystemConfigLoader::class);
        $loader->expects($this->exactly(2))->method('load')->with(null, null)->willReturn($expectedConfig);

        $store = new MemoizedSystemConfigStore();
        $memoized = new MemoizedSystemConfigLoader($loader, $store);
        $decorated = new DecoratedMemoizedResetTestSystemConfigLoader($memoized);
        $decorated->load(null);

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($store);
        $dispatcher->dispatch(new SystemConfigChangedEvent('example.enabled', false, null));

        static::assertSame($expectedConfig, $decorated->load(null));
    }

    public function testMemoizesTenantDefaultsIndependently(): void
    {
        $tenantA = Context::createTenantContext(Uuid::randomHex());
        $tenantB = Context::createTenantContext(Uuid::randomHex());

        $loader = $this->createMock(AbstractSystemConfigLoader::class);
        $loader->expects($this->exactly(2))
            ->method('load')
            ->willReturnCallback(static fn (?string $channelId, ?Context $context): array => [
                'tenant' => $context?->getTenantId(),
            ]);

        $service = new MemoizedSystemConfigLoader($loader, new MemoizedSystemConfigStore());

        static::assertSame(['tenant' => $tenantA->getTenantId()], $service->load(null, $tenantA));
        static::assertSame(['tenant' => $tenantB->getTenantId()], $service->load(null, $tenantB));
        static::assertSame(['tenant' => $tenantA->getTenantId()], $service->load(null, $tenantA));
    }
}

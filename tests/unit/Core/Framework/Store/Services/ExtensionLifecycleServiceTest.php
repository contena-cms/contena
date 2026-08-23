<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Store\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\PluginEntity;
use Contena\Core\Framework\Plugin\PluginLifecycleService;
use Contena\Core\Framework\Plugin\PluginManagementService;
use Contena\Core\Framework\Plugin\PluginService;
use Contena\Core\Framework\Store\Services\ExtensionLifecycleService;
use Contena\Core\Framework\Store\StoreException;

/**
 * @internal
 */
#[CoversClass(ExtensionLifecycleService::class)]
class ExtensionLifecycleServiceTest extends TestCase
{
    public function testInstallDelegatesToPluginLifecycle(): void
    {
        $context = Context::createDefaultContext();
        $plugin = new PluginEntity();

        $pluginService = $this->createMock(PluginService::class);
        $pluginService->expects($this->once())->method('getPluginByName')->with('ExamplePlugin', $context)->willReturn($plugin);

        $pluginLifecycle = $this->createMock(PluginLifecycleService::class);
        $pluginLifecycle->expects($this->once())->method('installPlugin')->with($plugin, $context);

        $service = new ExtensionLifecycleService(
            $pluginService,
            $pluginLifecycle,
            static::createStub(PluginManagementService::class),
        );

        $service->install('plugin', 'ExamplePlugin', $context);
    }

    public function testAppLifecycleIsNotPartOfThePlatform(): void
    {
        $service = new ExtensionLifecycleService(
            static::createStub(PluginService::class),
            static::createStub(PluginLifecycleService::class),
            static::createStub(PluginManagementService::class),
        );

        $this->expectExceptionObject(StoreException::invalidType('plugin', 'app'));

        $service->install('app', 'ExampleApp', Context::createDefaultContext());
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Store\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\PluginCollection;
use Contena\Core\Framework\Plugin\PluginEntity;
use Contena\Core\Framework\Store\Services\ExtensionDataProvider;
use Contena\Core\Framework\Store\Services\ExtensionLoader;
use Contena\Core\Framework\Store\Struct\ExtensionStruct;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\Service\ConfigurationService;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ExtensionDataProvider::class)]
#[CoversClass(ExtensionLoader::class)]
class ExtensionDataProviderTest extends TestCase
{
    public function testInstalledPluginsUseTheNativeExtensionStoreShape(): void
    {
        $plugin = new PluginEntity();
        $plugin->setId(Uuid::randomHex());
        $plugin->setName('ExamplePlugin');
        $plugin->setLabel('Example plugin');
        $plugin->setTranslated(['label' => 'Example plugin', 'description' => 'Example description']);
        $plugin->setAuthor('Contena');
        $plugin->setLicense('MIT');
        $plugin->setVersion('1.0.0');
        $plugin->setActive(true);
        $plugin->setManagedByComposer(false);
        $plugin->setIcon('cGx1Z2lu');
        $plugin->setIconRaw('plugin');

        $configurationService = $this->createMock(ConfigurationService::class);
        $configurationService->expects($this->once())
            ->method('checkConfiguration')
            ->with('ExamplePlugin.config')
            ->willReturn(true);

        /** @var StaticEntityRepository<PluginCollection> $repository */
        $repository = new StaticEntityRepository([new PluginCollection([$plugin])]);
        $provider = new ExtensionDataProvider(
            new ExtensionLoader($configurationService, static::createStub(LoggerInterface::class), new EventDispatcher()),
            $repository,
        );

        $extensions = $provider->getInstalledExtensions(Context::createDefaultContext());
        $extension = $extensions->get('ExamplePlugin');

        static::assertInstanceOf(ExtensionStruct::class, $extension);
        static::assertSame($plugin->getId(), $extension->getLocalId());
        static::assertSame('ExamplePlugin', $extension->getName());
        static::assertSame('Example plugin', $extension->getLabel());
        static::assertSame('Example description', $extension->getDescription());
        static::assertSame(ExtensionStruct::EXTENSION_TYPE_PLUGIN, $extension->getType());
        static::assertFalse($extension->isTheme());
        static::assertTrue($extension->isConfigurable());
    }
}

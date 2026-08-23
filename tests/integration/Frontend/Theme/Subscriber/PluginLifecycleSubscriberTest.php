<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Theme\Subscriber;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Migration\MigrationCollection;
use Contena\Core\Framework\Plugin;
use Contena\Core\Framework\Plugin\Context\ActivateContext;
use Contena\Core\Framework\Plugin\Context\UpdateContext;
use Contena\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPreUpdateEvent;
use Contena\Core\Framework\Plugin\PluginEntity;
use Contena\Core\Framework\Plugin\PluginLifecycleService;
use Contena\Core\Framework\Test\Plugin\PluginTestsHelper;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Frontend\Theme\FrontendPluginConfiguration\AbstractFrontendPluginConfigurationFactory;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\Subscriber\PluginLifecycleSubscriber;
use Contena\Frontend\Theme\ThemeLifecycleHandler;
use Contena\Frontend\Theme\ThemeLifecycleService;
use CtTestPlugin\CtTestPlugin;

/**
 * @internal
 */
class PluginLifecycleSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PluginTestsHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->addTestPluginToKernel(
            __DIR__ . '/../../../../../tests/integration/Core/Framework/Plugin/_fixtures/plugins/CtTestPlugin',
            'CtTestPlugin'
        );
    }

    public function testDoesNotAddPluginFrontendConfigurationToConfigurationCollectionIfItIsAddedAlready(): void
    {
        $context = Context::createDefaultContext();
        $event = new PluginPostActivateEvent(
            $this->getPlugin(),
            new ActivateContext(
                $this->createMock(Plugin::class),
                $context,
                '6.1.0',
                '1.0.0',
                $this->createMock(MigrationCollection::class)
            )
        );
        $frontendPluginConfigMock = new FrontendPluginConfiguration('CtTest');
        // Plugin frontend config is already added here
        $frontendPluginConfigCollection = new FrontendPluginConfigurationCollection([$frontendPluginConfigMock]);

        $pluginConfigurationFactory = $this->createMock(AbstractFrontendPluginConfigurationFactory::class);
        $pluginConfigurationFactory->method('createFromBundle')->willReturn($frontendPluginConfigMock);
        $frontendPluginRegistry = $this->createMock(FrontendPluginRegistry::class);
        $frontendPluginRegistry->method('getConfigurations')->willReturn($frontendPluginConfigCollection);
        $handler = $this->createMock(ThemeLifecycleHandler::class);
        $handler->expects($this->once())->method('handleThemeInstallOrUpdate')->with(
            $frontendPluginConfigMock,
            // This ensures the plugin frontend config is not added twice
            static::equalTo($frontendPluginConfigCollection),
            $context,
        );

        $subscriber = new PluginLifecycleSubscriber(
            $frontendPluginRegistry,
            __DIR__,
            $pluginConfigurationFactory,
            $handler,
            static::getContainer()->get(ThemeLifecycleService::class),
        );

        $subscriber->pluginPostActivate($event);
    }

    public function testAddsThePluginFrontendConfigurationToConfigurationCollectionIfItWasNotAddedAlready(): void
    {
        $context = Context::createDefaultContext();
        $event = new PluginPostActivateEvent(
            $this->getPlugin(),
            new ActivateContext(
                $this->createMock(Plugin::class),
                $context,
                '6.1.0',
                '1.0.0',
                $this->createMock(MigrationCollection::class)
            )
        );
        $frontendPluginConfigMock = new FrontendPluginConfiguration('CtTest');
        // Plugin frontend config is not added here
        $frontendPluginConfigCollection = new FrontendPluginConfigurationCollection([]);

        $pluginConfigurationFactory = $this->createMock(AbstractFrontendPluginConfigurationFactory::class);
        $pluginConfigurationFactory->method('createFromBundle')->willReturn($frontendPluginConfigMock);
        $frontendPluginRegistry = $this->createMock(FrontendPluginRegistry::class);
        $frontendPluginRegistry->method('getConfigurations')->willReturn($frontendPluginConfigCollection);
        $collectionWithPluginConfig = clone $frontendPluginConfigCollection;
        $collectionWithPluginConfig->add($frontendPluginConfigMock);
        $handler = $this->createMock(ThemeLifecycleHandler::class);
        $handler->expects($this->once())->method('handleThemeInstallOrUpdate')->with(
            $frontendPluginConfigMock,
            // This ensures the plugin frontend config was added in the subscriber
            static::equalTo($collectionWithPluginConfig),
            $context,
        );

        $subscriber = new PluginLifecycleSubscriber(
            $frontendPluginRegistry,
            __DIR__,
            $pluginConfigurationFactory,
            $handler,
            static::getContainer()->get(ThemeLifecycleService::class),
        );

        $subscriber->pluginPostActivate($event);
    }

    public function testThemeLifecycleIsNotCalledWhenDeactivatedUsingContextOnActivate(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);
        $event = new PluginPostActivateEvent(
            $this->getPlugin(),
            new ActivateContext(
                $this->createMock(Plugin::class),
                $context,
                '6.1.0',
                '1.0.0',
                $this->createMock(MigrationCollection::class)
            )
        );

        $handler = $this->createMock(ThemeLifecycleHandler::class);
        $handler->expects($this->never())->method('handleThemeInstallOrUpdate');

        $subscriber = new PluginLifecycleSubscriber(
            $this->createMock(FrontendPluginRegistry::class),
            __DIR__,
            $this->createMock(AbstractFrontendPluginConfigurationFactory::class),
            $handler,
            static::getContainer()->get(ThemeLifecycleService::class),
        );

        $subscriber->pluginPostActivate($event);
    }

    public function testThemeLifecycleIsNotCalledWhenDeactivatedUsingContextOnUpdate(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);
        $event = new PluginPreUpdateEvent(
            $this->getPlugin(),
            new UpdateContext(
                $this->createMock(Plugin::class),
                $context,
                '6.1.0',
                '1.0.0',
                $this->createMock(MigrationCollection::class),
                '1.0.1'
            )
        );

        $handler = $this->createMock(ThemeLifecycleHandler::class);
        $handler->expects($this->never())->method('handleThemeInstallOrUpdate');

        $subscriber = new PluginLifecycleSubscriber(
            $this->createMock(FrontendPluginRegistry::class),
            __DIR__,
            $this->createMock(AbstractFrontendPluginConfigurationFactory::class),
            $handler,
            static::getContainer()->get(ThemeLifecycleService::class),
        );

        $subscriber->pluginUpdate($event);
    }

    public function testPostUpdateDoesNothingWhenAssetBuildingIsDisabled(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);
        $event = new PluginPostUpdateEvent(
            $this->getPlugin(),
            new UpdateContext(
                $this->createMock(Plugin::class),
                $context,
                '6.1.0',
                '1.0.0',
                $this->createMock(MigrationCollection::class),
                '1.0.1'
            )
        );

        $registry = $this->createMock(FrontendPluginRegistry::class);
        $registry->expects($this->never())->method('getConfigurations');

        $handler = $this->createMock(ThemeLifecycleHandler::class);
        $handler->expects($this->never())->method('refreshAllActiveThemeImportMaps');

        $subscriber = new PluginLifecycleSubscriber(
            $registry,
            __DIR__,
            $this->createMock(AbstractFrontendPluginConfigurationFactory::class),
            $handler,
            static::getContainer()->get(ThemeLifecycleService::class),
        );

        $subscriber->pluginPostUpdate($event);
    }

    private function getPlugin(): PluginEntity
    {
        return new PluginEntity()
            ->assign([
                'name' => 'CtTestPlugin',
                'path' => new \ReflectionClass(CtTestPlugin::class)->getFileName(),
                'baseClass' => CtTestPlugin::class,
            ]);
    }
}

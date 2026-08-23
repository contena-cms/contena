<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Store\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\PluginCollection;
use Contena\Core\Framework\Plugin\PluginEntity;
use Contena\Core\Framework\Store\Event\ExtensionLoadedEvent;
use Contena\Core\Framework\Store\Services\ExtensionLoader;
use Contena\Core\System\SystemConfig\Service\ConfigurationService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ExtensionLoader::class)]
class ExtensionLoaderTest extends TestCase
{
    public function testLoadFromPluginCollectionContinuesOnError(): void
    {
        $configurationService = static::createStub(ConfigurationService::class);
        $configurationService
            ->method('checkConfiguration')
            ->willReturnCallback(static function (string $domain): bool {
                if ($domain === 'BrokenPlugin.config') {
                    throw new \RuntimeException('Invalid XML');
                }

                return true;
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Failed to load plugin extension data',
                static::callback(static fn (array $context): bool => $context['plugin'] === 'BrokenPlugin'
                    && $context['exception'] === 'Invalid XML'),
            );

        $loader = $this->createLoader(configurationService: $configurationService, logger: $logger);
        $extensions = $loader->loadFromPluginCollection(Context::createDefaultContext(), new PluginCollection([
            $this->createPlugin('WorkingPlugin'),
            $this->createPlugin('BrokenPlugin'),
            $this->createPlugin('AnotherWorkingPlugin'),
        ]));

        static::assertCount(2, $extensions);
        static::assertTrue($extensions->has('WorkingPlugin'));
        static::assertTrue($extensions->has('AnotherWorkingPlugin'));
        static::assertFalse($extensions->has('BrokenPlugin'));
    }

    #[TestDox('loadFromPluginCollection dispatches ExtensionLoadedEvent carrying the plugin source, struct and context')]
    public function testLoadFromPluginDispatchesEventWithPluginAndContext(): void
    {
        $captured = null;
        $context = Context::createDefaultContext();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ExtensionLoadedEvent::class,
            static function (ExtensionLoadedEvent $event) use (&$captured): void {
                $captured = $event;
            },
        );

        $this->createLoader($dispatcher)->loadFromPluginCollection(
            $context,
            new PluginCollection([$this->createPlugin('SomePlugin')]),
        );

        static::assertInstanceOf(ExtensionLoadedEvent::class, $captured);
        static::assertSame('SomePlugin', $captured->source->getName());
        static::assertSame('SomePlugin', $captured->extension->getName());
        static::assertSame($context, $captured->context);
    }

    #[TestDox('A plugin is flagged as theme when an ExtensionLoadedEvent listener sets it on the struct')]
    public function testLoadFromPluginMarksThemeWhenListenerFlagsIt(): void
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            ExtensionLoadedEvent::class,
            static fn (ExtensionLoadedEvent $event) => $event->extension->setIsTheme(true),
        );

        $extensions = $this->createLoader($dispatcher)->loadFromPluginCollection(
            Context::createDefaultContext(),
            new PluginCollection([$this->createPlugin('ThemePlugin')]),
        );

        $extension = $extensions->get('ThemePlugin');
        static::assertNotNull($extension);
        static::assertTrue($extension->isTheme());
    }

    #[TestDox('A plugin is not a theme when no listener flags the event')]
    public function testLoadFromPluginIsNotThemeWithoutListener(): void
    {
        $extensions = $this->createLoader()->loadFromPluginCollection(
            Context::createDefaultContext(),
            new PluginCollection([$this->createPlugin('PlainPlugin')]),
        );

        $extension = $extensions->get('PlainPlugin');
        static::assertNotNull($extension);
        static::assertFalse($extension->isTheme());
    }

    private function createLoader(
        ?EventDispatcherInterface $eventDispatcher = null,
        ?ConfigurationService $configurationService = null,
        ?LoggerInterface $logger = null,
    ): ExtensionLoader {
        return new ExtensionLoader(
            $configurationService ?? static::createStub(ConfigurationService::class),
            $logger ?? static::createStub(LoggerInterface::class),
            $eventDispatcher ?? new EventDispatcher(),
        );
    }

    private function createPlugin(string $name): PluginEntity
    {
        $plugin = new PluginEntity();
        $plugin->setUniqueIdentifier($name);
        $plugin->assign([
            'id' => $name,
            'name' => $name,
            'baseClass' => 'NonExistentClass\\' . $name,
            'version' => '1.0.0',
            'active' => true,
            'managedByComposer' => false,
            'path' => 'custom/plugins/' . $name,
            'author' => 'Test Author',
        ]);
        $plugin->setTranslated([
            'label' => $name . ' Label',
            'description' => $name . ' Description',
        ]);

        return $plugin;
    }
}

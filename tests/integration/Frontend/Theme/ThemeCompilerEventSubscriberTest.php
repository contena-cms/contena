<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Theme;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInputFactory;
use Contena\Core\Framework\Context;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Frontend\Event\ThemeCompilerConcatenatedStylesEvent;
use Contena\Frontend\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\MD5ThemePathBuilder;
use Contena\Frontend\Theme\ScssPhpCompiler;
use Contena\Frontend\Theme\ThemeCompiler;
use Contena\Frontend\Theme\ThemeFileResolver;
use Contena\Frontend\Theme\ThemeFilesystemResolver;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class ThemeCompilerEventSubscriberTest extends TestCase
{
    use KernelTestBehaviour;

    private ThemeCompiler $themeCompiler;

    private Filesystem $filesystem;

    private Filesystem $tempFilesystem;

    private Filesystem $assetFilesystem;

    private EventDispatcherInterface $eventDispatcher;

    private string $mockChannelId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->tempFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->assetFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->mockChannelId = '98432def39fc4624b33213a56b8c944d';
        $this->eventDispatcher = static::getContainer()->get('event_dispatcher');

        $this->themeCompiler = new ThemeCompiler(
            $this->filesystem,
            $this->tempFilesystem,
            $this->assetFilesystem,
            new CopyBatchInputFactory(),
            static::getContainer()->get(ThemeFileResolver::class),
            true,
            $this->eventDispatcher,
            static::getContainer()->get(ThemeFilesystemResolver::class),
            ['theme' => new UrlPackage(['http://localhost'], new EmptyVersionStrategy())],
            static::getContainer()->get(CacheInvalidator::class),
            static::createStub(LoggerInterface::class),
            new MD5ThemePathBuilder(),
            static::getContainer()->get(ScssPhpCompiler::class),
            [],
            false
        );
    }

    // ===================================
    // Event Subscriber Integration Tests
    // ===================================

    public function testEventSubscriberCanEnrichScssVariables(): void
    {
        $subscriber = new MockThemeVariablesSubscriber(
            static::getContainer()->get(SystemConfigService::class)
        );

        $variables = [
            'sw-color-brand-primary' => '#008490',
        ];

        $event = new ThemeCompilerEnrichScssVariablesEvent(
            $variables,
            $this->mockChannelId,
            Context::createDefaultContext()
        );

        $subscriber->onAddVariables($event);

        $result = $event->getVariables();

        static::assertArrayHasKey('mock-variable-black', $result);
        static::assertSame('#000000', $result['mock-variable-black']);
        static::assertArrayHasKey('mock-variable-special', $result);
        static::assertSame('\'Special value with quotes\'', $result['mock-variable-special']);
    }

    public function testEventSubscriberCanModifyConcatenatedStyles(): void
    {
        $subscriber = new MockThemeCompilerConcatenatedSubscriber();

        $styles = 'body { margin: 0; }';

        $event = new ThemeCompilerConcatenatedStylesEvent($styles, $this->mockChannelId);
        $subscriber->onGetConcatenatedStyles($event);

        $result = $event->getConcatenatedStyles();

        static::assertStringContainsString('body { margin: 0; }', $result);
        static::assertStringContainsString(MockThemeCompilerConcatenatedSubscriber::STYLES_CONCAT, $result);
    }

    public function testVariableEnrichmentEventAffectsCompiledOutput(): void
    {
        // Add a subscriber that enriches variables
        $subscriber = new MockThemeVariablesSubscriber(
            static::getContainer()->get(SystemConfigService::class)
        );
        EventHookDispatcher::fromContainer(static::getContainer())->subscribe($subscriber);

        $config = new FrontendPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'sw-color-brand-primary' => [
                    'name' => 'sw-color-brand-primary',
                    'type' => 'color',
                    'value' => '#008490',
                ],
            ],
        ]);

        $this->themeCompiler->compileTheme(
            $this->mockChannelId,
            'test-theme-id',
            $config,
            new FrontendPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        // Check that enriched variables were written
        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');
        static::assertStringContainsString('$mock-variable-black: #000000', $variablesContent);
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Theme;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInputFactory;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Event\ThemeCompilerConcatenatedStylesEvent;
use Contena\Frontend\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\MD5ThemePathBuilder;
use Contena\Frontend\Theme\ScssPhpCompiler;
use Contena\Frontend\Theme\ThemeCompiler;
use Contena\Frontend\Theme\ThemeFileResolver;
use Contena\Frontend\Theme\ThemeFilesystemResolver;
use Contena\Tests\Integration\Frontend\Theme\fixtures\MockThemeCompilerConcatenatedSubscriber;
use Contena\Tests\Integration\Frontend\Theme\fixtures\MockThemeVariablesSubscriber;
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

    private EventDispatcherInterface $eventDispatcher;

    private string $mockChannelId;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->tempFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $assetFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->mockChannelId = '98432def39fc4624b33213a56b8c944d';
        $this->eventDispatcher = static::getContainer()->get('event_dispatcher');

        $this->themeCompiler = new ThemeCompiler(
            $this->filesystem,
            $this->tempFilesystem,
            $assetFilesystem,
            new CopyBatchInputFactory(),
            static::getContainer()->get(ThemeFileResolver::class),
            true,
            $this->eventDispatcher,
            static::getContainer()->get(ThemeFilesystemResolver::class),
            ['theme' => new UrlPackage(['http://localhost'], new EmptyVersionStrategy())],
            static::getContainer()->get(CacheInvalidator::class),
            $this->createMock(LoggerInterface::class),
            new MD5ThemePathBuilder(),
            static::getContainer()->get(ScssPhpCompiler::class),
            [],
            false
        );
    }

    public function testEventSubscriberCanEnrichScssVariables(): void
    {
        $subscriber = new MockThemeVariablesSubscriber(
            static::getContainer()->get(SystemConfigService::class)
        );
        $event = new ThemeCompilerEnrichScssVariablesEvent(
            ['frontend-color-brand-primary' => '#008490'],
            $this->mockChannelId,
            Context::createDefaultContext()
        );

        $subscriber->onAddVariables($event);

        static::assertSame('#000000', $event->getVariables()['mock-variable-black']);
        static::assertSame('\'Special value with quotes\'', $event->getVariables()['mock-variable-special']);
    }

    public function testEventSubscriberCanModifyConcatenatedStyles(): void
    {
        $event = new ThemeCompilerConcatenatedStylesEvent('body { margin: 0; }', $this->mockChannelId);
        new MockThemeCompilerConcatenatedSubscriber()->onGetConcatenatedStyles($event);

        static::assertStringContainsString('body { margin: 0; }', $event->getConcatenatedStyles());
        static::assertStringContainsString(MockThemeCompilerConcatenatedSubscriber::STYLES_CONCAT, $event->getConcatenatedStyles());
    }

    public function testVariableEnrichmentEventAffectsCompiledOutput(): void
    {
        $subscriber = new MockThemeVariablesSubscriber(
            static::getContainer()->get(SystemConfigService::class)
        );
        $this->eventDispatcher->addSubscriber($subscriber);

        $config = new FrontendPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'frontend-color-brand-primary' => [
                    'name' => 'frontend-color-brand-primary',
                    'type' => 'color',
                    'value' => '#008490',
                ],
            ],
        ]);

        try {
            $this->themeCompiler->compileTheme(
                $this->mockChannelId,
                'test-theme-id',
                $config,
                new FrontendPluginConfigurationCollection(),
                false,
                Context::createDefaultContext()
            );

            static::assertStringContainsString(
                '$mock-variable-black: #000000',
                $this->tempFilesystem->read('theme-variables.scss')
            );
        } finally {
            $this->eventDispatcher->removeSubscriber($subscriber);
        }
    }
}

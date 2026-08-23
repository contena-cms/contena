<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;
use Contena\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInputFactory;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Contena\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Contena\Core\Framework\Util\Filesystem as ThemeFilesystem;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Contena\Core\Test\TestDefaults;
use Contena\Frontend\Event\ThemeCompilerConcatenatedStylesEvent;
use Contena\Frontend\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Contena\Frontend\Theme\Exception\ThemeCompileException;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FileCollection;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationFactory;
use Contena\Frontend\Theme\MD5ThemePathBuilder;
use Contena\Frontend\Theme\ScssPhpCompiler;
use Contena\Frontend\Theme\ThemeCompiler;
use Contena\Frontend\Theme\ThemeFileResolver;
use Contena\Frontend\Theme\ThemeFilesystemResolver;
use Contena\Tests\Integration\Frontend\Theme\fixtures\MockThemeCompilerConcatenatedSubscriber;
use Contena\Tests\Integration\Frontend\Theme\fixtures\MockThemeVariablesSubscriber;
use Contena\Tests\Unit\Frontend\Theme\fixtures\ThemeAndPlugin\AsyncPlugin\AsyncPlugin;
use Contena\Tests\Unit\Frontend\Theme\fixtures\ThemeAndPlugin\NotFoundPlugin\NotFoundPlugin;
use Contena\Tests\Unit\Frontend\Theme\fixtures\ThemeAndPlugin\TestTheme\TestTheme;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem as SymfonyFilesystem;

/**
 * @internal
 */
#[CoversClass(ThemeCompiler::class)]
class ThemeCompilerTest extends TestCase
{
    use EnvTestBehaviour;

    private string $mockChannelId;

    /**
     * @var ThemeFileResolver&Stub
     */
    private ThemeFileResolver $themeFileResolver;

    private Filesystem $filesystem;

    private Filesystem $tempFilesystem;

    /**
     * @var EventDispatcher&Stub
     */
    private EventDispatcher $eventDispatcher;

    /**
     * @var CacheInvalidator&Stub
     */
    private CacheInvalidator $cacheInvalidator;

    /**
     * @var LoggerInterface&Stub
     */
    private LoggerInterface $logger;

    /**
     * @var ScssPhpCompiler&Stub
     */
    private ScssPhpCompiler $scssPhpCompiler;

    private MD5ThemePathBuilder $pathBuilder;

    private ThemeFilesystemResolver&Stub $themeFilesystemResolver;

    /**
     * @var CopyBatchInputFactory&Stub
     */
    private CopyBatchInputFactory $copyBatchInputFactory;

    protected function setUp(): void
    {
        $this->themeFileResolver = static::createStub(ThemeFileResolver::class);
        $this->eventDispatcher = static::createStub(EventDispatcher::class);
        $this->cacheInvalidator = static::createStub(CacheInvalidator::class);
        $this->logger = static::createStub(LoggerInterface::class);
        $this->scssPhpCompiler = static::createStub(ScssPhpCompiler::class);
        $this->pathBuilder = new MD5ThemePathBuilder();
        $this->copyBatchInputFactory = static::createStub(CopyBatchInputFactory::class);
        $this->themeFilesystemResolver = static::createStub(ThemeFilesystemResolver::class);

        $this->filesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->tempFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->mockChannelId = '98432def39fc4624b33213a56b8c944d';
    }

    public function testThemeCompileExceptionIsThrownWhenFilesAreNotResolved(): void
    {
        $this->themeFileResolver->method('resolveStyleFiles')->willThrowException(new \InvalidArgumentException());
        $compiler = $this->getThemeCompiler();

        $config = new FrontendPluginConfiguration('test');
        $config->setName('faultyTheme');

        $this->expectExceptionObject(new ThemeCompileException('faultyTheme'));
        $compiler->compileTheme(
            TestDefaults::CHANNEL,
            'test',
            $config,
            new FrontendPluginConfigurationCollection(),
            true,
            Context::createDefaultContext()
        );
    }

    public function testThemeCompileExceptionIsThrownWhenConcatenateFails(): void
    {
        $this->themeFileResolver->method('resolveFiles')->willReturn(
            [ThemeFileResolver::STYLE_FILES => FileCollection::createFromArray(['foo'])]
        );

        $this->eventDispatcher->method('dispatch')->willThrowException(new \Exception());

        $compiler = $this->getThemeCompiler();

        $config = new FrontendPluginConfiguration('test');
        $config->setName('faultyTheme');

        $this->expectExceptionObject(new ThemeCompileException('faultyTheme'));
        $compiler->compileTheme(
            TestDefaults::CHANNEL,
            'test',
            $config,
            new FrontendPluginConfigurationCollection(),
            true,
            Context::createDefaultContext()
        );
    }

    public function testThemeCompileExceptionIsThrownWhenCollectCompiledFilesFails(): void
    {
        $this->themeFileResolver->method('resolveFiles')->willReturn(
            [ThemeFileResolver::STYLE_FILES => FileCollection::createFromArray(['foo'])]
        );

        $this->copyBatchInputFactory->method('fromDirectory')->willThrowException(new \Exception());

        $compiler = $this->getThemeCompiler();

        $config = new FrontendPluginConfiguration('test');
        $config->setName('faultyTheme');
        $config->setAssetPaths(['bla']);

        $this->expectExceptionObject(new ThemeCompileException('faultyTheme'));
        $compiler->compileTheme(
            TestDefaults::CHANNEL,
            'test',
            $config,
            new FrontendPluginConfigurationCollection(),
            true,
            Context::createDefaultContext()
        );
    }

    public function testFormatVariablesArrayConvertsToNonAssociativeArrayWithValidScssSyntax(): void
    {
        $formatVariables = new \ReflectionMethod(ThemeCompiler::class, 'formatVariables');

        $variables = [
            'ct-color-brand-primary' => '#008490',
            'ct-color-brand-secondary' => '#526e7f',
            'ct-border-color' => '#bcc1c7',
        ];

        $actual = $formatVariables->invoke($this->getThemeCompiler(), $variables);

        $expected = [
            '$ct-color-brand-primary: #008490;',
            '$ct-color-brand-secondary: #526e7f;',
            '$ct-border-color: #bcc1c7;',
        ];

        static::assertSame($expected, $actual);
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('configForDumpVariables')]
    public function testDumpVariables(array $config, string $expected): void
    {
        $themeConfig = new FrontendPluginConfiguration('test');
        $themeConfig->setThemeConfig($config);

        $this->getThemeCompiler()->compileTheme(
            TestDefaults::CHANNEL,
            'themeId',
            $themeConfig,
            new FrontendPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        static::assertSame($expected, $this->tempFilesystem->read('theme-variables.scss'));
        static::assertSame($expected, $this->tempFilesystem->read('theme-variables/themeId.scss'));
    }

    public static function configForDumpVariables(): \Generator
    {
        yield 'finds config fields and returns string with scss variables' => [
            [
                'fields' => [
                    'contena-color-brand-primary' => [
                        'name' => 'contena-color-brand-primary',
                        'type' => 'color',
                        'value' => '#008490',
                    ],
                    'contena-color-brand-secondary' => [
                        'name' => 'contena-color-brand-secondary',
                        'type' => 'color',
                        'value' => '#526e7f',
                    ],
                    'contena-border-color' => [
                        'name' => 'contena-border-color',
                        'type' => 'color',
                        'value' => '#bcc1c7',
                    ],
                    'contena-custom-header' => [
                        'name' => 'contena-custom-header',
                        'type' => 'checkbox',
                        'value' => false,
                    ],
                    'contena-custom-footer' => [
                        'name' => 'contena-custom-header',
                        'type' => 'checkbox',
                        'value' => true,
                    ],
                    'contena-custom-navigation' => [
                        'name' => 'contena-custom-header',
                        'type' => 'switch',
                        'value' => false,
                    ],
                    'contena-custom-content-card' => [
                        'name' => 'contena-custom-header',
                        'type' => 'switch',
                        'value' => true,
                    ],
                    'contena-text-field' => [
                        'name' => 'contena-text-field',
                        'type' => 'text',
                        'value' => '2px solid #000',
                    ],
                    'contena-custom-textarea' => [
                        'name' => 'contena-custom-textarea',
                        'type' => 'textarea',
                        'value' => '123',
                    ],
                    'contena-invalid-textarea' => [
                        'name' => 'contena-invalid-textarea',
                        'type' => 'media',
                        'value' => [123],
                    ],
                    'contena-custom-url' => [
                        'name' => 'contena-custom-url',
                        'type' => 'url',
                        'value' => 'https://www.contena.cn',
                    ],
                    'contena-custom-media' => [
                        'name' => 'contena-custom-media',
                        'type' => 'media',
                        'value' => '456',
                    ],
                    'contena-invalid-media' => [
                        'name' => 'contena-invalid-media',
                        'type' => 'media',
                        'value' => [false],
                    ],
                    'contena-invalid-type' => [
                        'name' => 'contena-invalid-type',
                        'value' => [false],
                    ],
                    'contena-multi-test' => [
                        'name' => 'contena-multi-test',
                        'type' => 'text',
                        'value' => [
                            'top',
                            'bottom',
                        ],
                        'custom' => [
                            'componentName' => 'contena-multi-select',
                            'options' => [
                                [
                                    'value' => 'bottom',
                                ],
                                [
                                    'value' => 'top',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            <<<PHP_EOL
// ATTENTION! This file is auto generated by the Contena\Frontend\Theme\ThemeCompiler and should not be edited.

\$theme-id: themeId;
\$contena-color-brand-primary: #008490;
\$contena-color-brand-secondary: #526e7f;
\$contena-border-color: #bcc1c7;
\$contena-custom-header: 0;
\$contena-custom-footer: 1;
\$contena-custom-navigation: 0;
\$contena-custom-content-card: 1;
\$contena-text-field: 2px solid #000;
\$contena-custom-textarea: '123';
\$contena-custom-url: 'https://www.contena.cn';
\$contena-custom-media: '456';
\$contena-asset-theme-url: 'http://localhost';

PHP_EOL,
        ];

        yield 'ignores fields with scss config property set to false' => [
            [
                'fields' => [
                    'contena-color-brand-primary' => [
                        'name' => 'contena-color-brand-primary',
                        'type' => 'color',
                        'value' => '#008490',
                    ],
                    'contena-color-brand-secondary' => [
                        'name' => 'contena-color-brand-secondary',
                        'type' => 'color',
                        'value' => '#526e7f',
                    ],
                    // Prevent adding field as sass variable
                    'contena-ignore-me' => [
                        'name' => 'contena-border-color',
                        'type' => 'text',
                        'value' => 'Foo bar',
                        'scss' => false,
                    ],
                ],
            ],
            <<<PHP_EOL
// ATTENTION! This file is auto generated by the Contena\Frontend\Theme\ThemeCompiler and should not be edited.

\$theme-id: themeId;
\$contena-color-brand-primary: #008490;
\$contena-color-brand-secondary: #526e7f;
\$contena-asset-theme-url: 'http://localhost';

PHP_EOL,
        ];
        yield 'HasNoConfigFieldsAndReturnsOnlyDefaultVariables' => [
            [
                'blocks' => [
                    'themeColors' => [
                        'label' => [
                            'en-GB' => 'Theme colours',
                            'de-DE' => 'Theme-Farben',
                        ],
                    ],
                    'typography' => [
                        'label' => [
                            'en-GB' => 'Typography',
                            'de-DE' => 'Typografie',
                        ],
                    ],
                ],
            ],
            '// ATTENTION! This file is auto generated by the Contena\Frontend\Theme\ThemeCompiler and should not be edited.

$theme-id: themeId;
$contena-asset-theme-url: \'http://localhost\';
',
        ];
        yield 'MayHaveZeroValueButNotNull' => [
            [
                'fields' => [
                    'contena-zero-margin' => [
                        'name' => 'contena-zero-margin',
                        'type' => 'text',
                        'value' => 0,
                    ],
                    'contena-null-margin' => [
                        'name' => 'contena-null-margin',
                        'type' => 'text',
                        'value' => null,
                    ],
                    'contena-unset-margin' => [
                        'name' => 'contena-unset-margin',
                        'type' => 'text',
                    ],
                    'contena-empty-margin' => [
                        'name' => 'contena-empty-margin',
                        'type' => 'text',
                        'value' => '',
                    ],
                ],
            ],
            <<<PHP_EOL
// ATTENTION! This file is auto generated by the Contena\Frontend\Theme\ThemeCompiler and should not be edited.

\$theme-id: themeId;
\$contena-zero-margin: 0;
\$contena-null-margin: null;
\$contena-unset-margin: null;
\$contena-empty-margin: null;
\$contena-asset-theme-url: 'http://localhost';

PHP_EOL,
        ];
    }

    public function testScssVariablesEventAddsNewVariablesToArray(): void
    {
        $subscriber = new MockThemeVariablesSubscriber(static::createStub(SystemConfigService::class));

        $variables = [
            'contena-color-brand-primary' => '#008490',
            'contena-color-brand-secondary' => '#526e7f',
            'contena-border-color' => '#bcc1c7',
        ];

        $event = new ThemeCompilerEnrichScssVariablesEvent($variables, $this->mockChannelId, Context::createDefaultContext());
        $subscriber->onAddVariables($event);

        $actual = $event->getVariables();

        $expected = [
            'contena-color-brand-primary' => '#008490',
            'contena-color-brand-secondary' => '#526e7f',
            'contena-border-color' => '#bcc1c7',
            'mock-variable-black' => '#000000',
            'mock-variable-special' => '\'Special value with quotes\'',
        ];

        static::assertSame($expected, $actual);
    }

    public function testConcatenatedStylesEventPassThru(): void
    {
        $subscriber = new MockThemeCompilerConcatenatedSubscriber();

        $styles = 'body {}';

        $event = new ThemeCompilerConcatenatedStylesEvent($styles, $this->mockChannelId);
        $subscriber->onGetConcatenatedStyles($event);
        $actual = $event->getConcatenatedStyles();

        $expected = $styles . MockThemeCompilerConcatenatedSubscriber::STYLES_CONCAT;

        static::assertSame($expected, $actual);
    }

    public function testCompileWithoutAssets(): void
    {
        $this->themeFileResolver->method('resolveFiles')->willReturn([
            ThemeFileResolver::SCRIPT_FILES => new FileCollection(),
            ThemeFileResolver::STYLE_FILES => new FileCollection(),
        ]);

        $compiler = $this->getThemeCompiler();

        $config = new FrontendPluginConfiguration('test');
        $config->setAssetPaths(['bla']);

        $pathBuilder = new MD5ThemePathBuilder();
        static::assertSame('9a11a759d278b4a55cb5e2c3414733c1', $pathBuilder->assemblePath(TestDefaults::CHANNEL, 'test'));

        try {
            $pathBuilder->getDecorated();
        } catch (\Throwable $e) {
            static::assertInstanceOf(DecorationPatternException::class, $e);
        }

        $compiler->compileTheme(
            TestDefaults::CHANNEL,
            'test',
            $config,
            new FrontendPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        static::assertTrue($this->filesystem->has('theme/9a11a759d278b4a55cb5e2c3414733c1'));
    }

    public function testAssetPathWillBeAbsoluteConverted(): void
    {
        $config = new FrontendPluginConfiguration('test');
        $config->setAssetPaths(['assets']);

        $fs = new StaticFilesystem(['Resources/assets' => 'directory']);

        $themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $themeFilesystemResolver->expects($this->once())
            ->method('getFilesystemForFrontendConfig')
            ->with($config)
            ->willReturn($fs);

        $this->themeFileResolver->method('resolveFiles')->willReturn([
            ThemeFileResolver::SCRIPT_FILES => new FileCollection(),
            ThemeFileResolver::STYLE_FILES => new FileCollection(),
        ]);

        $this->filesystem->createDirectory('temp');
        $this->filesystem->write('temp/test.png', '');
        $png = $this->filesystem->readStream('temp/test.png');

        $this->copyBatchInputFactory->method('fromDirectory')->willReturn(
            [
                new CopyBatchInput($png, ['theme/9a11a759d278b4a55cb5e2c3414733c1/assets/test.png']),
            ]
        );

        $compiler = $this->getThemeCompiler(themeFilesystemResolver: $themeFilesystemResolver);

        $pathBuilder = new MD5ThemePathBuilder();
        static::assertSame('9a11a759d278b4a55cb5e2c3414733c1', $pathBuilder->assemblePath(TestDefaults::CHANNEL, 'test'));

        try {
            $pathBuilder->getDecorated();
        } catch (\Throwable $e) {
            static::assertInstanceOf(DecorationPatternException::class, $e);
        }

        $compiler->compileTheme(
            TestDefaults::CHANNEL,
            'test',
            $config,
            new FrontendPluginConfigurationCollection(),
            true,
            Context::createDefaultContext()
        );

        static::assertTrue($this->filesystem->fileExists('theme/9a11a759d278b4a55cb5e2c3414733c1/assets/test.png'));
    }

    public function testExistingFilesAreNotDeletedOnCompileError(): void
    {
        $this->themeFileResolver->method('resolveFiles')->willReturn(
            [
                ThemeFileResolver::SCRIPT_FILES => new FileCollection(),
                ThemeFileResolver::STYLE_FILES => new FileCollection()]
        );

        $this->filesystem->createDirectory('theme/9a11a759d278b4a55cb5e2c3414733c1');
        $this->filesystem->write('theme/9a11a759d278b4a55cb5e2c3414733c1/all.js', '');

        $scssPhpCompiler = $this->createMock(ScssPhpCompiler::class);
        $scssPhpCompiler->expects($this->once())->method('compileString')->willThrowException(new \Exception());

        $compiler = $this->getThemeCompiler(scssPhpCompiler: $scssPhpCompiler);

        $config = new FrontendPluginConfiguration('test');
        $config->setAssetPaths(['assets']);

        $pathBuilder = new MD5ThemePathBuilder();
        static::assertSame('9a11a759d278b4a55cb5e2c3414733c1', $pathBuilder->assemblePath(TestDefaults::CHANNEL, 'test'));

        $wasThrown = false;

        try {
            $compiler->compileTheme(
                TestDefaults::CHANNEL,
                'test',
                $config,
                new FrontendPluginConfigurationCollection(),
                true,
                Context::createDefaultContext()
            );
        } catch (ThemeCompileException) {
            $wasThrown = true;
        }

        static::assertTrue($wasThrown);
        static::assertTrue($this->filesystem->fileExists('theme/9a11a759d278b4a55cb5e2c3414733c1/all.js'));
    }

    public function testNewFilesAreDeletedOnCompileError(): void
    {
        $this->themeFileResolver->method('resolveFiles')->willReturn(
            [
                ThemeFileResolver::SCRIPT_FILES => new FileCollection(),
                ThemeFileResolver::STYLE_FILES => new FileCollection()]
        );

        $this->filesystem->createDirectory('theme/current');
        $this->filesystem->write('theme/current/all.js', '');

        $copyBatchInputFactory = $this->createMock(CopyBatchInputFactory::class);
        $copyBatchInputFactory->expects($this->never())
            ->method('fromDirectory');

        $scssPhpCompiler = $this->createMock(ScssPhpCompiler::class);
        $scssPhpCompiler->expects($this->once())->method('compileString')->willThrowException(new \Exception());

        $this->pathBuilder = $this->createMock(MD5ThemePathBuilder::class);
        $this->pathBuilder->method('assemblePath')->willReturn('current');
        $this->pathBuilder->method('generateNewPath')->willReturn('new');
        $this->pathBuilder->expects($this->never())->method('saveSeed');

        $compiler = $this->getThemeCompiler(
            copyBatchInputFactory: $copyBatchInputFactory,
            scssPhpCompiler: $scssPhpCompiler,
        );

        $config = new FrontendPluginConfiguration('test');
        $config->setAssetPaths(['assets']);

        $wasThrown = false;

        try {
            $compiler->compileTheme(
                TestDefaults::CHANNEL,
                'test',
                $config,
                new FrontendPluginConfigurationCollection(),
                true,
                Context::createDefaultContext()
            );
        } catch (ThemeCompileException) {
            $wasThrown = true;
        }

        static::assertTrue($wasThrown);
        static::assertTrue($this->filesystem->fileExists('theme/current/all.js'));
        static::assertFalse($this->filesystem->fileExists('theme/new/all.js'));
    }

    public function testOldThemeFilesAreDeletedDelayedOnThemeCompileSuccess(): void
    {
        $this->themeFileResolver->method('resolveFiles')->willReturn(
            [
                ThemeFileResolver::SCRIPT_FILES => new FileCollection(),
                ThemeFileResolver::STYLE_FILES => new FileCollection()]
        );

        $this->filesystem->createDirectory('theme/current');
        $this->filesystem->write('theme/current/all.js', '');

        $scssPhpCompiler = $this->createMock(ScssPhpCompiler::class);
        $scssPhpCompiler->expects($this->once())->method('compileString')->willReturn('');

        $this->pathBuilder = $this->createMock(MD5ThemePathBuilder::class);
        $this->pathBuilder->method('assemblePath')->willReturn('current');
        $this->pathBuilder->expects($this->once())
            ->method('generateNewPath')
            ->with(
                TestDefaults::CHANNEL,
                'test'
            )
            ->willReturn('new');
        $this->pathBuilder->expects($this->once())
            ->method('saveSeed')
            ->with(TestDefaults::CHANNEL, 'test');

        $compiler = $this->getThemeCompiler(scssPhpCompiler: $scssPhpCompiler);

        $config = new FrontendPluginConfiguration('test');
        $config->setAssetPaths(['assets']);

        $compiler->compileTheme(
            TestDefaults::CHANNEL,
            'test',
            $config,
            new FrontendPluginConfigurationCollection(),
            true,
            Context::createDefaultContext()
        );

        static::assertTrue($this->filesystem->fileExists('theme/current/all.js'));
    }

    public function testCopyScriptFilesToTheme(): void
    {
        $this->themeFileResolver->method('resolveFiles')->willReturn(
            [
                ThemeFileResolver::SCRIPT_FILES => new FileCollection(),
                ThemeFileResolver::STYLE_FILES => new FileCollection()]
        );

        $distLocation = __DIR__ . '/fixtures/ThemeAndPlugin/TestTheme/Resources/app/frontend/dist/frontend/js/test-theme';
        $this->filesystem->createDirectory($distLocation);
        $this->filesystem->write($distLocation . '/test-theme.js', '');

        $scssPhpCompiler = $this->createMock(ScssPhpCompiler::class);
        $scssPhpCompiler->expects($this->once())->method('compileString')->willReturn('');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $this->setEnvVars([
            'V6_6_0_0' => 1,
        ]);

        $projectDir = __DIR__ . '/fixtures';
        $themeFilesystemResolver = $this->createMock(ThemeFilesystemResolver::class);
        $compiler = $this->getThemeCompiler(
            themeFilesystemResolver: $themeFilesystemResolver,
            logger: $logger,
            scssPhpCompiler: $scssPhpCompiler,
        );

        $filesystems = [
            'AsyncPlugin' => new ThemeFilesystem(__DIR__ . '/fixtures/ThemeAndPlugin/AsyncPlugin'),
            'TestTheme' => new ThemeFilesystem(__DIR__ . '/fixtures/ThemeAndPlugin/TestTheme'),
            'NotFoundPlugin' => new ThemeFilesystem(__DIR__ . '/fixtures/ThemeAndPlugin/NotFoundPlugin'),
        ];

        $themeFilesystemResolver->expects($this->exactly(\count($filesystems)))
            ->method('getFilesystemForFrontendConfig')
            ->willReturnCallback(static fn (FrontendPluginConfiguration $config) => $filesystems[$config->getTechnicalName()]);

        $configurationFactory = new FrontendPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new SymfonyFilesystem(),
        );

        $themePluginBundle = new TestTheme();
        $asyncPluginBundle = new AsyncPlugin(true, $projectDir . 'fixtures/ThemeAndPlugin/AsyncPlugin');
        $notFoundPluginBundle = new NotFoundPlugin(
            true,
            $projectDir . 'fixtures/ThemeAndPlugin/NotFoundPlugin'
        );
        $testTheme = $configurationFactory->createFromBundle($themePluginBundle);
        $asyncPlugin = $configurationFactory->createFromBundle($asyncPluginBundle);
        $notFoundPlugin = $configurationFactory->createFromBundle($notFoundPluginBundle);
        $scripts = new FileCollection();
        $scripts = $scripts::createFromArray([
            'Resources/app/frontend/src/plugins/lorem-ipsum/plugin.js',
        ]);
        $notFoundPlugin->setScriptFiles($scripts);

        $configCollection = new FrontendPluginConfigurationCollection();
        $configCollection->add($testTheme);
        $configCollection->add($asyncPlugin);
        $configCollection->add($notFoundPlugin);

        $compiler->compileTheme(
            TestDefaults::CHANNEL,
            'TestTheme',
            $testTheme,
            $configCollection,
            true,
            Context::createDefaultContext()
        );

        $themeBasePath = '/theme/2fb1d60e66e241fe65bcedc271cc2174';
        $asyncMainJsInTheme = $themeBasePath . '/js/async-plugin/async-plugin.js';
        $asyncAnotherJsFileInTheme = $themeBasePath . '/js/async-plugin/custom_plugins_AsyncPlugin_src_Resources_app_frontend_src_plugins_lorem-ipsum_plugin_js.js';
        $themeMainJsInTheme = $themeBasePath . '/js/test-theme/test-theme.js';

        static::assertTrue($this->filesystem->directoryExists($distLocation));
        static::assertTrue($this->filesystem->fileExists($distLocation . '/test-theme.js'));
        static::assertTrue($this->filesystem->fileExists($asyncMainJsInTheme));
        static::assertTrue($this->filesystem->fileExists($asyncAnotherJsFileInTheme));
        static::assertTrue($this->filesystem->fileExists($themeMainJsInTheme));
    }

    public function testKeepConfigurationCollectionWithGetScriptDistFolders(): void
    {
        $compiler = $this->getThemeCompiler();

        $configurationFactory = new FrontendPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new SymfonyFilesystem(),
        );

        $themePluginBundle = new TestTheme();
        $testTheme = $configurationFactory->createFromBundle($themePluginBundle);

        $configCollection = new FrontendPluginConfigurationCollection();
        $configCollection->add($testTheme);

        $testTheme->setScriptFiles(
            FileCollection::createFromArray([
                'Resources/app/frontend/src/plugins/lorem-ipsum/plugin.js',
                '@Frontend',
            ])
        );

        $currentConfigCollection = clone $configCollection;

        $compiler->compileTheme(
            TestDefaults::CHANNEL,
            'TestTheme',
            $testTheme,
            $configCollection,
            true,
            Context::createDefaultContext()
        );

        // There should be no side effects on the configuration collection
        static::assertEquals($currentConfigCollection, $configCollection);
    }

    /**
     * @param array<string> $mappings
     */
    #[DataProvider('importPathsProvider')]
    public function testGetResolveImportPathsCallbackReturnsNull(array $mappings, string $originPath): void
    {
        $compiler = $this->getThemeCompiler();
        $closure = $compiler->getResolveImportPathsCallback($mappings);

        static::assertNull($closure($originPath));
    }

    public static function importPathsProvider(): \Generator
    {
        yield 'no mapping' => [
            [],
            'fake_path',
        ];
        yield 'wrong path without extension' => [
            ['fake_path' => 'fake_path'],
            '~fake_path',
        ];
        yield 'wrong path with min extension' => [
            ['fake_path' => 'fake_path'],
            '~fake_path.min',
        ];
        yield 'wrong path with zip extension' => [
            ['fake_path' => 'fake_path'],
            '~fake_path.zip',
        ];
    }

    protected function getThemeCompiler(
        ?CopyBatchInputFactory $copyBatchInputFactory = null,
        ?ThemeFilesystemResolver $themeFilesystemResolver = null,
        ?LoggerInterface $logger = null,
        ?ScssPhpCompiler $scssPhpCompiler = null,
    ): ThemeCompiler {
        return new ThemeCompiler(
            $this->filesystem,
            $this->tempFilesystem,
            static::createStub(FilesystemOperator::class),
            $copyBatchInputFactory ?? $this->copyBatchInputFactory,
            $this->themeFileResolver,
            true,
            $this->eventDispatcher,
            $themeFilesystemResolver ?? $this->themeFilesystemResolver,
            ['theme' => new UrlPackage(['http://localhost'], new EmptyVersionStrategy())],
            $this->cacheInvalidator,
            $logger ?? $this->logger,
            $this->pathBuilder,
            $scssPhpCompiler ?? $this->scssPhpCompiler,
            [],
            false
        );
    }
}

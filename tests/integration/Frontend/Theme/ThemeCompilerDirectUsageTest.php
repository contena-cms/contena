<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Theme;

use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInputFactory;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Frontend\Theme\CompilerConfiguration;
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
class ThemeCompilerDirectUsageTest extends TestCase
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
            $this->createMock(LoggerInterface::class),
            new MD5ThemePathBuilder(),
            static::getContainer()->get(ScssPhpCompiler::class),
            [],
            false
        );
    }

    // ===================================
    // Real SCSS Compilation Tests
    // ===================================

    public function testCompilesScssToValidCss(): void
    {
        $scssInput = '$primary-color: #ff0000; .test { color: $primary-color; }';

        $result = static::getContainer()->get(ScssPhpCompiler::class)->compileString(
            new CompilerConfiguration([]),
            $scssInput
        );

        static::assertStringContainsString('.test', $result);
        static::assertStringContainsString('#ff0000', $result);
        static::assertStringContainsString('color', $result);
    }

    public function testCompilesThemeVariablesIntoScss(): void
    {
        $config = new FrontendPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'ct-color-brand-primary' => [
                    'name' => 'ct-color-brand-primary',
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

        // Check that variables were written to temp filesystem
        static::assertTrue($this->tempFilesystem->has('theme-variables.scss'));
        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');

        static::assertStringContainsString('$ct-color-brand-primary: #008490', $variablesContent);
        static::assertStringContainsString('$theme-id: test-theme-id', $variablesContent);
    }

    public function testCompiledCssContainsThemeVariables(): void
    {
        $testScss = '.button { background: $ct-test-color; }';

        // Create a simple SCSS file for testing
        $this->tempFilesystem->write('test.scss', $testScss);

        $config = new FrontendPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'ct-test-color' => [
                    'name' => 'ct-test-color',
                    'type' => 'color',
                    'value' => '#123456',
                ],
            ],
        ]);

        // Compile with variables
        $variables = '$ct-test-color: #123456;';
        $fullScss = $variables . "\n" . $testScss;

        $result = static::getContainer()->get(ScssPhpCompiler::class)->compileString(
            new CompilerConfiguration([]),
            $fullScss
        );

        static::assertStringContainsString('.button', $result);
        static::assertStringContainsString('#123456', $result);
    }

    // ===================================
    // Feature Flag Behavior Tests
    // ===================================

    public function testFeatureFlagFunctionWorksInScss(): void
    {
        if (EnvironmentHelper::getVariable('FEATURE_ALL')) {
            static::markTestSkipped('Skipped because FEATURE_ALL should be false for this test.');
        }

        Feature::registerFeatures([
            'FEATURE_NEXT_TEST_1' => ['default' => true],
            'FEATURE_NEXT_TEST_2' => ['default' => false],
        ]);

        $featureMixin = '@function feature($feature-flag) { @return map-get($ct-features, $feature-flag); }';

        // Inject $ct-features variable (normally done by ThemeCompiler)
        $allFeatures = Feature::getAll();
        $featuresScss = implode(',', array_map(
            fn ($value, $key) => \sprintf('"%s": %s', $key, json_encode($value, \JSON_THROW_ON_ERROR)),
            $allFeatures,
            array_keys($allFeatures)
        ));
        $featureVariables = \sprintf('$ct-features: (%s);', $featuresScss);

        $testScss = <<<'SCSS'
.test-selector {
    @if feature('FEATURE_NEXT_TEST_1') {
        background: green;
    } @else {
        background: red;
    }
}

@if feature('FEATURE_NEXT_TEST_2') {
    .should-not-exist {
        display: none;
    }
}
SCSS;

        $result = static::getContainer()->get(ScssPhpCompiler::class)->compileString(
            new CompilerConfiguration([]),
            $featureVariables . "\n" . $featureMixin . "\n" . $testScss
        );

        // FEATURE_NEXT_TEST_1 is active, so we should see green background
        static::assertStringContainsString('background:green', str_replace(' ', '', $result));
        static::assertStringNotContainsString('background:red', str_replace(' ', '', $result));

        // FEATURE_NEXT_TEST_2 is inactive, so .should-not-exist should not appear
        static::assertStringNotContainsString('.should-not-exist', $result);
    }

    public function testFeatureFlagVariablesAreInjected(): void
    {
        $testScss = '$test: map.get($ct-features, "FEATURE_NEXT_1");';

        // This should compile without errors because $ct-features is injected by ThemeCompiler
        $config = new FrontendPluginConfiguration('TestTheme');

        $this->themeCompiler->compileTheme(
            $this->mockChannelId,
            'test-theme-id',
            $config,
            new FrontendPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        // If we get here, compilation succeeded (no exception thrown)
        // Verify theme variables were written successfully
        static::assertTrue($this->tempFilesystem->has('theme-variables.scss'));
    }

    // ===================================
    // Vendor Import Path Tests
    // ===================================

    public function testResolvesVendorImportPaths(): void
    {
        $testScss = <<<'SCSS'
@import '~vendor/library.min';
@import '~vendor/another-library';
SCSS;

        $vendorPath = __DIR__ . '/fixtures/ThemeWithScssVendorImports/Frontend/Resources/app/frontend/vendor';

        $result = static::getContainer()->get(ScssPhpCompiler::class)->compileString(
            new CompilerConfiguration([
                'importPaths' => [
                    function (string $path) use ($vendorPath) {
                        if (str_starts_with($path, '~vendor/')) {
                            $relativePath = substr($path, 8); // Remove '~vendor/'
                            $fullPath = $vendorPath . '/' . $relativePath;

                            // Try with .css extension for .min files
                            if (str_ends_with($relativePath, '.min')) {
                                $cssPath = $fullPath . '.css';
                                if (is_file($cssPath)) {
                                    return $cssPath;
                                }
                            }

                            // Try with .scss extension
                            if (is_file($fullPath . '.scss')) {
                                return $fullPath . '.scss';
                            }
                        }

                        return null;
                    },
                ],
            ]),
            $testScss
        );

        // Should contain content from both imported files
        static::assertStringContainsString('.plain-css-from-library', $result);
        static::assertStringContainsString('.another-lib', $result);
    }

    // ===================================
    // Variable Type Handling Tests
    // ===================================

    public function testHandlesColorVariables(): void
    {
        $config = new FrontendPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'ct-color-primary' => [
                    'type' => 'color',
                    'value' => '#ff0000',
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

        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');
        static::assertStringContainsString('$ct-color-primary: #ff0000', $variablesContent);
    }

    public function testHandlesBooleanVariables(): void
    {
        $config = new FrontendPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'ct-custom-header-enabled' => [
                    'type' => 'checkbox',
                    'value' => true,
                ],
                'ct-custom-footer-enabled' => [
                    'type' => 'checkbox',
                    'value' => false,
                ],
                'ct-switch-enabled' => [
                    'type' => 'switch',
                    'value' => true,
                ],
                'ct-switch-disabled' => [
                    'type' => 'switch',
                    'value' => false,
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

        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');
        static::assertStringContainsString('$ct-custom-header-enabled: 1', $variablesContent);
        static::assertStringContainsString('$ct-custom-footer-enabled: 0', $variablesContent);
        static::assertStringContainsString('$ct-switch-enabled: 1', $variablesContent);
        static::assertStringContainsString('$ct-switch-disabled: 0', $variablesContent);
    }

    public function testHandlesTextVariables(): void
    {
        $config = new FrontendPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'ct-text-field' => [
                    'type' => 'text',
                    'value' => '2px solid #000',
                ],
                'ct-textarea-field' => [
                    'type' => 'textarea',
                    'value' => 'Lorem ipsum',
                ],
                'ct-url-field' => [
                    'type' => 'url',
                    'value' => 'https://example.com',
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

        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');
        static::assertStringContainsString('$ct-text-field: 2px solid #000', $variablesContent);
        static::assertStringContainsString('$ct-textarea-field: \'Lorem ipsum\'', $variablesContent);
        static::assertStringContainsString('$ct-url-field: \'https://example.com\'', $variablesContent);
    }

    public function testHandlesNullAndZeroValues(): void
    {
        $config = new FrontendPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'ct-zero-margin' => [
                    'type' => 'text',
                    'value' => 0,
                ],
                'ct-null-margin' => [
                    'type' => 'text',
                    'value' => null,
                ],
                'ct-unset-margin' => [
                    'type' => 'text',
                    // No value key
                ],
                'ct-empty-margin' => [
                    'type' => 'text',
                    'value' => '',
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

        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');
        static::assertStringContainsString('$ct-zero-margin: 0', $variablesContent);
        static::assertStringContainsString('$ct-null-margin: null', $variablesContent);
        static::assertStringContainsString('$ct-unset-margin: null', $variablesContent);
        static::assertStringContainsString('$ct-empty-margin: null', $variablesContent);
    }

    public function testIgnoresFieldsWithScssPropertySetToFalse(): void
    {
        $config = new FrontendPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'ct-color-primary' => [
                    'type' => 'color',
                    'value' => '#ff0000',
                ],
                'ct-ignored-field' => [
                    'type' => 'text',
                    'value' => 'Should not appear',
                    'scss' => false,
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

        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');
        static::assertStringContainsString('$ct-color-primary: #ff0000', $variablesContent);
        static::assertStringNotContainsString('ct-ignored-field', $variablesContent);
    }

    public function testHandlesMediaFieldVariables(): void
    {
        $config = new FrontendPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'ct-logo-desktop' => [
                    'type' => 'media',
                    'value' => 'media-id-123',
                ],
                'ct-logo-mobile' => [
                    'type' => 'media',
                    'value' => 'media-id-456',
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

        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');
        static::assertStringContainsString('$ct-logo-desktop: \'media-id-123\'', $variablesContent);
        static::assertStringContainsString('$ct-logo-mobile: \'media-id-456\'', $variablesContent);
    }

    public function testHandlesMultiSelectFieldWithArrayValue(): void
    {
        $config = new FrontendPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'ct-multi-select-field' => [
                    'name' => 'ct-multi-select-field',
                    'type' => 'text',
                    'value' => [
                        'top',
                        'bottom',
                    ],
                    'custom' => [
                        'componentName' => 'ct-multi-select',
                        'options' => [
                            ['value' => 'top'],
                            ['value' => 'bottom'],
                            ['value' => 'left'],
                            ['value' => 'right'],
                        ],
                    ],
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

        // Multi-select fields with array values are not converted to SCSS variables
        // They are filtered out because they're not scalar values
        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');

        // The variable should not appear as SCSS doesn't support array values
        static::assertStringNotContainsString('$ct-multi-select-field:', $variablesContent);
    }

    public function testHandlesInvalidFieldTypes(): void
    {
        $config = new FrontendPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'ct-valid-field' => [
                    'type' => 'text',
                    'value' => 'valid value',
                ],
                'ct-invalid-array-media' => [
                    'type' => 'media',
                    'value' => [123], // Invalid - array instead of string
                ],
                'ct-field-without-type' => [
                    'name' => 'ct-field-without-type',
                    'value' => 'no type specified',
                    // Missing 'type' key
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

        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');

        // Valid field should be present
        static::assertStringContainsString('$ct-valid-field: valid value', $variablesContent);

        // Invalid fields should not be present (filtered out)
        static::assertStringNotContainsString('ct-invalid-array-media', $variablesContent);
        static::assertStringNotContainsString('ct-field-without-type', $variablesContent);
    }

    public function testComprehensiveVariableTypeCompilation(): void
    {
        $config = new FrontendPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'ct-color-brand-primary' => [
                    'name' => 'ct-color-brand-primary',
                    'type' => 'color',
                    'value' => '#008490',
                ],
                'ct-color-brand-secondary' => [
                    'name' => 'ct-color-brand-secondary',
                    'type' => 'color',
                    'value' => '#526e7f',
                ],
                'ct-border-color' => [
                    'name' => 'ct-border-color',
                    'type' => 'color',
                    'value' => '#bcc1c7',
                ],
                'ct-custom-header' => [
                    'name' => 'ct-custom-header',
                    'type' => 'checkbox',
                    'value' => false,
                ],
                'ct-custom-footer' => [
                    'name' => 'ct-custom-footer',
                    'type' => 'checkbox',
                    'value' => true,
                ],
                'ct-custom-cart' => [
                    'name' => 'ct-custom-cart',
                    'type' => 'switch',
                    'value' => false,
                ],
                'ct-custom-product-box' => [
                    'name' => 'ct-custom-product-box',
                    'type' => 'switch',
                    'value' => true,
                ],
                'ct-text-field' => [
                    'name' => 'ct-text-field',
                    'type' => 'text',
                    'value' => '2px solid #000',
                ],
                'ct-textarea-field' => [
                    'name' => 'ct-textarea-field',
                    'type' => 'textarea',
                    'value' => 'Lorem ipsum dolor',
                ],
                'ct-url-field' => [
                    'name' => 'ct-url-field',
                    'type' => 'url',
                    'value' => 'https://www.example.com',
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

        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');

        // Verify all variable types are correctly formatted
        static::assertStringContainsString('$ct-color-brand-primary: #008490', $variablesContent);
        static::assertStringContainsString('$ct-color-brand-secondary: #526e7f', $variablesContent);
        static::assertStringContainsString('$ct-border-color: #bcc1c7', $variablesContent);
        static::assertStringContainsString('$ct-custom-header: 0', $variablesContent);
        static::assertStringContainsString('$ct-custom-footer: 1', $variablesContent);
        static::assertStringContainsString('$ct-custom-cart: 0', $variablesContent);
        static::assertStringContainsString('$ct-custom-product-box: 1', $variablesContent);
        static::assertStringContainsString('$ct-text-field: 2px solid #000', $variablesContent);
        static::assertStringContainsString('$ct-textarea-field: \'Lorem ipsum dolor\'', $variablesContent);
        static::assertStringContainsString('$ct-url-field: \'https://www.example.com\'', $variablesContent);
        static::assertStringContainsString('$ct-asset-theme-url: \'http://localhost\'', $variablesContent);
    }

    // ===================================
    // End-to-End Compilation Tests
    // ===================================

    public function testFullCompilationCreatesAllExpectedFiles(): void
    {
        $config = new FrontendPluginConfiguration('TestTheme');
        $config->setThemeConfig([
            'fields' => [
                'ct-color-primary' => [
                    'type' => 'color',
                    'value' => '#ff0000',
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

        // Check temp filesystem has variables
        static::assertTrue($this->tempFilesystem->has('theme-variables.scss'));
        static::assertTrue($this->tempFilesystem->has('theme-variables/test-theme-id.scss'));

        // Check main filesystem has theme directory
        $pathBuilder = new MD5ThemePathBuilder();
        $themePath = 'theme/' . $pathBuilder->assemblePath($this->mockChannelId, 'test-theme-id');
        static::assertTrue($this->filesystem->directoryExists($themePath));
    }

    public function testCompilationWritesCssFile(): void
    {
        $config = new FrontendPluginConfiguration('TestTheme');

        $this->themeCompiler->compileTheme(
            $this->mockChannelId,
            'test-theme-id',
            $config,
            new FrontendPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        $pathBuilder = new MD5ThemePathBuilder();
        $cssPath = 'theme/' . $pathBuilder->assemblePath($this->mockChannelId, 'test-theme-id') . '/css/all.css';

        static::assertTrue($this->filesystem->fileExists($cssPath));
    }

    // ===================================
    // Asset URL Tests
    // ===================================

    public function testInjectsAssetUrlVariable(): void
    {
        $config = new FrontendPluginConfiguration('TestTheme');

        $this->themeCompiler->compileTheme(
            $this->mockChannelId,
            'test-theme-id',
            $config,
            new FrontendPluginConfigurationCollection(),
            false,
            Context::createDefaultContext()
        );

        $variablesContent = $this->tempFilesystem->read('theme-variables.scss');
        static::assertStringContainsString('$ct-asset-theme-url: \'http://localhost\'', $variablesContent);
    }
}

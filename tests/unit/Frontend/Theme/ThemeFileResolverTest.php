<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Contena\Core\Kernel;
use Contena\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Contena\Frontend\Theme\Exception\ThemeCompileException;
use Contena\Frontend\Theme\Exception\ThemeException;
use Contena\Frontend\Theme\FrontendPluginConfiguration\File;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FileCollection;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationFactory;
use Contena\Frontend\Theme\ThemeFileResolver;
use Contena\Frontend\Theme\ThemeFilesystemResolver;
use Contena\Tests\Unit\Frontend\Theme\fixtures\MockFrontend\MockFrontend;
use Contena\Tests\Unit\Frontend\Theme\fixtures\SimplePlugin\SimplePlugin;
use Contena\Tests\Unit\Frontend\Theme\fixtures\ThemeNotIncludingPluginJsAndCss\ThemeNotIncludingPluginJsAndCss;
use Contena\Tests\Unit\Frontend\Theme\fixtures\ThemeWithBundleRelativeFiles\ThemeWithBundleRelativeFiles;
use Contena\Tests\Unit\Frontend\Theme\fixtures\ThemeWithFrontendBootstrapScss\ThemeWithFrontendBootstrapScss;
use Contena\Tests\Unit\Frontend\Theme\fixtures\ThemeWithFrontendSkinScss\ThemeWithFrontendSkinScss;
use Contena\Tests\Unit\Frontend\Theme\fixtures\ThemeWithInvalidBundleReference\ThemeWithInvalidBundleReference;
use Contena\Tests\Unit\Frontend\Theme\fixtures\ThemeWithMultiInheritance\ThemeWithMultiInheritance;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(ThemeFileResolver::class)]
class ThemeFileResolverTest extends TestCase
{
    public function testBundleRelativeFileThrowsExceptionForMissingFileInExistingBundle(): void
    {
        $themePluginBundle = new ThemeWithBundleRelativeFiles();
        $frontendBundle = new MockFrontend();

        $factory = new FrontendPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $config->setStyleFiles(
            FileCollection::createFromArray(['@MockFrontend/app/frontend/src/scss/does-not-exist.scss'])
        );
        $frontend = $factory->createFromBundle($frontendBundle);

        $configCollection = new FrontendPluginConfigurationCollection([$config, $frontend]);

        $kernel = static::createStub(Kernel::class);
        $kernel->method('getBundles')->willReturn([
            'ThemeWithBundleRelativeFiles' => $themePluginBundle,
            'MockFrontend' => $frontendBundle,
        ]);
        $kernel->method('getBundle')->willReturnMap([
            ['ThemeWithBundleRelativeFiles', $themePluginBundle],
            ['MockFrontend', $frontendBundle],
        ]);

        $resolver = new ThemeFileResolver(new ThemeFilesystemResolver($kernel));

        $this->expectExceptionObject(
            ThemeException::themeCompileException(
                'ThemeWithBundleRelativeFiles',
                'Unable to resolve file "@MockFrontend/app/frontend/src/scss/does-not-exist.scss". File does not exist.'
            )
        );
        $resolver->resolveStyleFiles($config, $configCollection, false);
    }

    public function testNamespaceReferenceThrowsExceptionWhenThemeIsMissing(): void
    {
        $config = new FrontendPluginConfiguration('TestTheme');
        $config->setStyleFiles(FileCollection::createFromArray(['@NonExistentTheme']));
        $config->setScriptFiles(new FileCollection());

        $resolver = new ThemeFileResolver(static::createStub(ThemeFilesystemResolver::class));

        $this->expectExceptionObject(ThemeException::couldNotFindThemeByName('NonExistentTheme'));
        $resolver->resolveStyleFiles($config, new FrontendPluginConfigurationCollection([$config]), false);
    }

    public function testResolveScriptFilesAddsOwnEntryAfterIncludedThemeWhenIncludeComesFirst(): void
    {
        $config = new FrontendPluginConfiguration('TestTheme');
        $ownEntry = tempnam(sys_get_temp_dir(), 'theme-file-resolver-own-entry-');
        if ($ownEntry === false) {
            static::fail('Could not create temporary file for own entry.');
        }
        $includedEntry = tempnam(sys_get_temp_dir(), 'theme-file-resolver-included-entry-');
        if ($includedEntry === false) {
            @unlink($ownEntry);
            static::fail('Could not create temporary file for included entry.');
        }

        $config->setFrontendEntryFilepath($ownEntry);
        $config->setScriptFiles(FileCollection::createFromArray(['@Frontend', '/tmp/should-be-skipped.js']));

        $frontend = new FrontendPluginConfiguration('Frontend');
        $frontend->setFrontendEntryFilepath($includedEntry);
        $frontend->setScriptFiles(new FileCollection());

        $filesystem = static::createStub(\Contena\Core\Framework\Util\Filesystem::class);
        $filesystem->method('has')->willReturn(false);

        $themeFilesystemResolver = static::createStub(ThemeFilesystemResolver::class);
        $themeFilesystemResolver->method('getFilesystemForFrontendConfig')->willReturn($filesystem);

        $resolver = new ThemeFileResolver($themeFilesystemResolver);
        $configCollection = new FrontendPluginConfigurationCollection([$config, $frontend]);

        try {
            $result = $resolver->resolveScriptFiles($config, $configCollection, true);

            static::assertSame([$includedEntry, $ownEntry], $result->getFilepaths());
            static::assertSame('frontend', $result->first()?->assetName);
            static::assertSame($config->getAssetName(), $result->last()?->assetName);
        } finally {
            @unlink($ownEntry);
            @unlink($includedEntry);
        }
    }

    public function testResolveStyleFilesReturnsEmptyCollectionForCircularThemeIncludes(): void
    {
        $config = new FrontendPluginConfiguration('TestTheme');
        $config->setStyleFiles(FileCollection::createFromArray(['@OtherTheme']));
        $config->setScriptFiles(new FileCollection());

        $otherConfig = new FrontendPluginConfiguration('OtherTheme');
        $otherConfig->setStyleFiles(FileCollection::createFromArray(['@TestTheme']));
        $otherConfig->setScriptFiles(new FileCollection());

        $resolver = new ThemeFileResolver(static::createStub(ThemeFilesystemResolver::class));
        $result = $resolver->resolveStyleFiles(
            $config,
            new FrontendPluginConfigurationCollection([$config, $otherConfig]),
            false
        );

        static::assertCount(0, $result);
    }

    public function testConvertPathsToAbsoluteAlsoConvertsResolveMappingEntries(): void
    {
        $config = new FrontendPluginConfiguration('TestTheme');
        $file = new File('app/frontend/src/scss/base.scss', ['vendor' => 'app/frontend/vendor']);
        $files = new FileCollection();
        $files->add($file);
        $config->setStyleFiles($files);

        $existingFilePath = tempnam(sys_get_temp_dir(), 'theme-file-resolver-');
        if ($existingFilePath === false) {
            static::fail('Could not create temporary file for test.');
        }

        $filesystem = static::createStub(\Contena\Core\Framework\Util\Filesystem::class);
        $filesystem->method('has')->willReturnMap([
            ['Resources', 'app/frontend/src/scss/base.scss', true],
            ['Resources', 'app/frontend/vendor', true],
        ]);
        $filesystem->method('realpath')->willReturnMap([
            ['Resources', 'app/frontend/src/scss/base.scss', $existingFilePath],
            ['Resources', 'app/frontend/vendor', '/tmp/Resources/app/frontend/vendor'],
        ]);

        $themeFilesystemResolver = static::createStub(ThemeFilesystemResolver::class);
        $themeFilesystemResolver->method('getFilesystemForFrontendConfig')->willReturn($filesystem);

        $resolver = new ThemeFileResolver($themeFilesystemResolver);
        $configCollection = new FrontendPluginConfigurationCollection([$config]);

        try {
            $resolvedFiles = $resolver->resolveFiles($config, $configCollection, false);

            static::assertSame([$existingFilePath], $resolvedFiles[ThemeFileResolver::STYLE_FILES]->getFilepaths());
            static::assertSame(
                ['vendor' => '/tmp/Resources/app/frontend/vendor'],
                $resolvedFiles[ThemeFileResolver::STYLE_FILES]->getResolveMappings()
            );
        } finally {
            @unlink($existingFilePath);
        }
    }

    public function testResolvedFilesIncludeSkinScssPath(): void
    {
        $themePluginBundle = new ThemeWithFrontendSkinScss();
        $frontendBundle = new MockFrontend();

        $factory = new FrontendPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $frontend = $factory->createFromBundle($frontendBundle);

        $configCollection = new FrontendPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($frontend);

        $kernel = static::createStub(Kernel::class);

        $kernel->method('getBundles')->willReturn([
            'ThemeWithFrontendSkinScss' => $themePluginBundle,
            'MockFrontend' => $frontendBundle,
        ]);

        $kernel->method('getBundle')->willReturnMap([
            ['ThemeWithFrontendSkinScss', $themePluginBundle],
            ['MockFrontend', $frontendBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $kernel
        );

        $resolvedFiles = new ThemeFileResolver($themeFilesystemResolver)->resolveFiles(
            $config,
            $configCollection,
            false
        );

        $actual = json_encode($resolvedFiles, \JSON_PRETTY_PRINT);
        $expected = '/Resources\/app\/frontend\/src\/scss\/skin\/contena\/_base.scss';

        static::assertStringContainsString($expected, (string) $actual);
    }

    public function testResolvedFilesDoNotIncludeSkinScssPath(): void
    {
        $themePluginBundle = new ThemeWithFrontendBootstrapScss();
        $frontendBundle = new MockFrontend();

        $factory = new FrontendPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $frontend = $factory->createFromBundle($frontendBundle);

        $configCollection = new FrontendPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($frontend);

        $kernel = static::createStub(Kernel::class);
        $kernel->method('getBundles')->willReturn([
            'ThemeWithFrontendBootstrapScss' => $themePluginBundle,
            'MockFrontend' => $frontendBundle,
        ]);

        $kernel->method('getBundle')->willReturnMap([
            ['ThemeWithFrontendBootstrapScss', $themePluginBundle],
            ['MockFrontend', $frontendBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $kernel
        );

        $resolvedFiles = new ThemeFileResolver($themeFilesystemResolver)->resolveFiles(
            $config,
            $configCollection,
            false
        );

        $actual = json_encode($resolvedFiles, \JSON_PRETTY_PRINT);
        $notExpected = '/Resources\/app\/frontend\/src\/scss\/skin\/contena\/_base.scss';

        static::assertStringNotContainsString($notExpected, (string) $actual);
    }

    public function testResolvedFilesDontContainDuplicates(): void
    {
        $themePluginBundle = new ThemeWithMultiInheritance(true, __DIR__ . '/fixtures/SimplePlugin');
        $frontendBundle = new MockFrontend();
        $pluginBundle = new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin');

        $factory = new FrontendPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $frontend = $factory->createFromBundle($frontendBundle);
        $plugin = $factory->createFromBundle($pluginBundle);

        $configCollection = new FrontendPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($frontend);
        $configCollection->add($plugin);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->once())->method('getBundles')->willReturn([
            'ThemeWithMultiInheritance' => $themePluginBundle,
            'MockFrontend' => $frontendBundle,
            'SimplePlugin' => $pluginBundle,
        ]);

        $kernel->method('getBundle')->willReturnMap([
            ['ThemeWithMultiInheritance', $themePluginBundle],
            ['MockFrontend', $frontendBundle],
            ['SimplePlugin', $pluginBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $kernel
        );

        $resolvedFiles = new ThemeFileResolver($themeFilesystemResolver)->resolveFiles(
            $config,
            $configCollection,
            false
        );
        $scriptFiles = $resolvedFiles['script'];
        $actual = $scriptFiles->getFilepaths();
        $expected = array_unique($scriptFiles->getFilepaths());

        static::assertSame($expected, $actual);
    }

    public function testParentThemeIncludesPlugins(): void
    {
        $themePluginBundle = new ThemeNotIncludingPluginJsAndCss();
        $frontendBundle = new MockFrontend();
        $pluginBundle = new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin');

        $factory = new FrontendPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $frontend = $factory->createFromBundle($frontendBundle);
        $plugin = $factory->createFromBundle($pluginBundle);

        $configCollection = new FrontendPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($frontend);
        $configCollection->add($plugin);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->once())->method('getBundles')->willReturn([
            'ThemeNotIncludingPluginJsAndCss' => $themePluginBundle,
            'MockFrontend' => $frontendBundle,
            'SimplePlugin' => $pluginBundle,
        ]);

        $kernel->method('getBundle')->willReturnMap([
            ['ThemeNotIncludingPluginJsAndCss', $themePluginBundle],
            ['MockFrontend', $frontendBundle],
            ['SimplePlugin', $pluginBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $kernel
        );

        $resolvedFiles = new ThemeFileResolver($themeFilesystemResolver)->resolveFiles(
            $config,
            $configCollection,
            false
        );

        $scriptFiles = $resolvedFiles['script'];
        $pluginScriptFile = 'SimplePlugin/Resources/app/frontend/dist/frontend/js/simple-plugin/simple-plugin.js';
        $pluginScriptIncluded = false;

        foreach ($scriptFiles->getFilepaths() as $path) {
            if (mb_stripos((string) $path, $pluginScriptFile) !== false) {
                $pluginScriptIncluded = true;

                break;
            }
        }

        static::assertTrue($pluginScriptIncluded);

        $styleFiles = $resolvedFiles['style'];
        $pluginEntryStyleFile = 'SimplePlugin/Resources/app/frontend/src/scss/base.scss';
        $pluginStyleIncluded = false;

        foreach ($styleFiles->getFilepaths() as $path) {
            if (mb_stripos((string) $path, $pluginEntryStyleFile) !== false) {
                $pluginStyleIncluded = true;

                break;
            }
        }

        static::assertTrue($pluginStyleIncluded);
    }

    public function testResolveFilesDoesntAffectPassedArguments(): void
    {
        $themePluginBundle = new ThemeWithFrontendSkinScss();
        $frontendBundle = new MockFrontend();

        $factory = new FrontendPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new Filesystem(),
        );
        $config = $factory->createFromBundle($themePluginBundle);
        $frontend = $factory->createFromBundle($frontendBundle);

        $configCollection = new FrontendPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($frontend);

        $firstFile = $config->getStyleFiles()->first();
        static::assertNotNull($firstFile);
        $currentPath = $firstFile->getFilepath();

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->once())->method('getBundles')->willReturn([
            'ThemeWithFrontendSkinScss' => $themePluginBundle,
            'MockFrontend' => $frontendBundle,
        ]);

        $kernel->method('getBundle')->willReturnMap([
            ['ThemeWithFrontendSkinScss', $themePluginBundle],
            ['MockFrontend', $frontendBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $kernel
        );

        new ThemeFileResolver($themeFilesystemResolver)->resolveFiles(
            $config,
            $configCollection,
            false
        );

        // Path is still relative
        static::assertSame($currentPath, $config->getStyleFiles()->first()?->getFilepath());

        $config->setScriptFiles(new FileCollection());
        $config->setFrontendEntryFilepath(__FILE__);

        new ThemeFileResolver($themeFilesystemResolver)->resolveFiles(
            $config,
            $configCollection,
            true
        );

        static::assertSame($currentPath, $config->getStyleFiles()->first()?->getFilepath());
    }

    public function testCircularReferencePreventionReturnsEmptyCollection(): void
    {
        $themePluginBundle = new ThemeWithMultiInheritance(true, __DIR__ . '/fixtures/SimplePlugin');
        $frontendBundle = new MockFrontend();

        $factory = new FrontendPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $frontend = $factory->createFromBundle($frontendBundle);

        $configCollection = new FrontendPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($frontend);

        $kernel = static::createStub(Kernel::class);
        $kernel->method('getBundles')->willReturn([
            'ThemeWithMultiInheritance' => $themePluginBundle,
            'MockFrontend' => $frontendBundle,
        ]);

        $kernel->method('getBundle')->willReturnMap([
            ['ThemeWithMultiInheritance', $themePluginBundle],
            ['MockFrontend', $frontendBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $kernel
        );

        $resolver = new ThemeFileResolver($themeFilesystemResolver);

        // This should not cause infinite loop - circular references are handled internally
        $result = $resolver->resolveScriptFiles($config, $configCollection, false);

        static::assertGreaterThan(0, $result->count());
    }

    public function testFileDeduplicationAcrossNamespaces(): void
    {
        $themePluginBundle = new ThemeWithMultiInheritance(true, __DIR__ . '/fixtures/SimplePlugin');
        $frontendBundle = new MockFrontend();
        $pluginBundle = new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin');

        $factory = new FrontendPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $frontend = $factory->createFromBundle($frontendBundle);
        $plugin = $factory->createFromBundle($pluginBundle);

        $configCollection = new FrontendPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($frontend);
        $configCollection->add($plugin);

        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->once())->method('getBundles')->willReturn([
            'ThemeWithMultiInheritance' => $themePluginBundle,
            'MockFrontend' => $frontendBundle,
            'SimplePlugin' => $pluginBundle,
        ]);

        $kernel->method('getBundle')->willReturnMap([
            ['ThemeWithMultiInheritance', $themePluginBundle],
            ['MockFrontend', $frontendBundle],
            ['SimplePlugin', $pluginBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $kernel
        );

        $resolvedFiles = new ThemeFileResolver($themeFilesystemResolver)->resolveFiles(
            $config,
            $configCollection,
            false
        );

        $styleFiles = $resolvedFiles['style'];
        $stylePaths = $styleFiles->getFilepaths();

        // Check that all paths are unique
        static::assertCount(\count(array_unique($stylePaths)), $stylePaths, 'Style files should not contain duplicates');

        $scriptFiles = $resolvedFiles['script'];
        $scriptPaths = $scriptFiles->getFilepaths();

        // Check that all paths are unique
        static::assertCount(\count(array_unique($scriptPaths)), $scriptPaths, 'Script files should not contain duplicates');
    }

    public function testBundleRelativeFileResolution(): void
    {
        $themePluginBundle = new ThemeWithBundleRelativeFiles();
        $frontendBundle = new MockFrontend();

        $factory = new FrontendPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $frontend = $factory->createFromBundle($frontendBundle);

        $configCollection = new FrontendPluginConfigurationCollection();
        $configCollection->add($config);
        $configCollection->add($frontend);

        $kernel = static::createStub(Kernel::class);
        $kernel->method('getBundles')->willReturn([
            'ThemeWithBundleRelativeFiles' => $themePluginBundle,
            'MockFrontend' => $frontendBundle,
        ]);

        $kernel->method('getBundle')->willReturnMap([
            ['ThemeWithBundleRelativeFiles', $themePluginBundle],
            ['MockFrontend', $frontendBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $kernel
        );

        $resolvedFiles = new ThemeFileResolver($themeFilesystemResolver)->resolveFiles(
            $config,
            $configCollection,
            false
        );

        $styleFiles = $resolvedFiles['style'];
        $paths = $styleFiles->getFilepaths();

        // Check that overrides.scss is resolved
        $overridesFound = false;
        $overridesPosition = -1;
        foreach ($paths as $index => $path) {
            if (str_contains((string) $path, 'overrides.scss')) {
                $overridesFound = true;
                $overridesPosition = $index;
                break;
            }
        }
        static::assertTrue($overridesFound, 'Bundle-relative file @MockFrontend/app/frontend/src/scss/overrides.scss should be resolved');

        // Check that overrides.scss appears only once (not duplicated when @MockFrontend is expanded)
        $overridesCount = 0;
        foreach ($paths as $path) {
            if (str_contains((string) $path, 'overrides.scss')) {
                ++$overridesCount;
            }
        }
        static::assertSame(1, $overridesCount, 'overrides.scss should appear only once (no duplication)');

        // Check that overrides.scss appears before base.scss (order preservation)
        $basePosition = -1;
        foreach ($paths as $index => $path) {
            if (str_contains((string) $path, 'base.scss')) {
                $basePosition = $index;
                break;
            }
        }

        if ($basePosition !== -1) {
            static::assertLessThan($basePosition, $overridesPosition, 'Bundle-relative file should appear in order before full bundle expansion');
        }

        // Check that custom.scss from the theme itself is also included
        $customFound = false;
        foreach ($paths as $path) {
            if (str_contains((string) $path, 'custom.scss')) {
                $customFound = true;
                break;
            }
        }
        static::assertTrue($customFound, 'Direct file reference custom.scss should be resolved');
    }

    public function testBundleRelativeFileThrowsExceptionForMissingBundle(): void
    {
        $themePluginBundle = new ThemeWithInvalidBundleReference();

        $factory = new FrontendPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);

        $configCollection = new FrontendPluginConfigurationCollection();
        $configCollection->add($config);

        $kernel = static::createStub(Kernel::class);
        $kernel->method('getBundles')->willReturn([
            'ThemeWithInvalidBundleReference' => $themePluginBundle,
        ]);

        $kernel->method('getBundle')->willReturnMap([
            ['ThemeWithInvalidBundleReference', $themePluginBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver(
            $kernel
        );

        $resolver = new ThemeFileResolver($themeFilesystemResolver);

        $this->expectExceptionObject(
            ThemeException::couldNotFindThemeByName('NonExistentBundle')
        );

        $resolver->resolveStyleFiles($config, $configCollection, false);
    }

    public function testFrontendBootstrapNamespaceResolvesToBaseScssWithVendorMapping(): void
    {
        $config = new FrontendPluginConfiguration('TestTheme');
        $config->setStyleFiles(FileCollection::createFromArray(['@FrontendBootstrap']));
        $config->setScriptFiles(new FileCollection());

        $configCollection = new FrontendPluginConfigurationCollection([$config]);

        $themeFilesystemResolver = static::createStub(ThemeFilesystemResolver::class);
        $resolver = new ThemeFileResolver($themeFilesystemResolver);

        $result = $resolver->resolveStyleFiles($config, $configCollection, false);

        static::assertCount(1, $result);
        $file = $result->first();
        static::assertNotNull($file);
        static::assertStringEndsWith('Resources/app/frontend/src/scss/base.scss', $file->getFilepath());
        static::assertArrayHasKey('vendor', $file->getResolveMapping());
        static::assertStringEndsWith('Resources/app/frontend/vendor', $file->getResolveMapping()['vendor']);
    }

    public function testDirectFileMissingMatchingOldJsStructureThrowsException(): void
    {
        $config = new FrontendPluginConfiguration('TestTheme');

        // Path ends with "test-plugin/test-plugin.css" — matches the old-JS-structure pattern → throw
        $file = new File('/nonexistent/test-plugin/test-plugin.css', [], 'test-plugin');
        $fileCollection = new FileCollection();
        $fileCollection->add($file);
        $config->setStyleFiles($fileCollection);
        $config->setScriptFiles(new FileCollection());

        $configCollection = new FrontendPluginConfigurationCollection([$config]);

        $themeFilesystemResolver = static::createStub(ThemeFilesystemResolver::class);
        $themeFilesystemResolver->method('getFilesystemForFrontendConfig')->willReturn(new StaticFilesystem([]));

        $resolver = new ThemeFileResolver($themeFilesystemResolver);

        $this->expectException(ThemeCompileException::class);
        $resolver->resolveStyleFiles($config, $configCollection, false);
    }

    public function testDirectFileMissingNotMatchingOldJsStructureIsSilentlySkipped(): void
    {
        $config = new FrontendPluginConfiguration('TestTheme');

        // assetName is 'test-plugin', but file is 'other-file.css' — no old-JS-structure match → silent skip
        $file = new File('/nonexistent/other-file.css', [], 'test-plugin');
        $fileCollection = new FileCollection();
        $fileCollection->add($file);
        $config->setStyleFiles($fileCollection);
        $config->setScriptFiles(new FileCollection());

        $configCollection = new FrontendPluginConfigurationCollection([$config]);

        $themeFilesystemResolver = static::createStub(ThemeFilesystemResolver::class);
        $themeFilesystemResolver->method('getFilesystemForFrontendConfig')->willReturn(new StaticFilesystem([]));

        $resolver = new ThemeFileResolver($themeFilesystemResolver);

        $result = $resolver->resolveStyleFiles($config, $configCollection, false);

        static::assertCount(0, $result, 'Missing file with non-matching path structure should be silently skipped');
    }

    public function testPluginsNamespaceExpandsAllNonThemePlugins(): void
    {
        $pluginBundle = new SimplePlugin(true, __DIR__ . '/fixtures/SimplePlugin');

        $factory = new FrontendPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new Filesystem(),
        );

        // Theme that directly declares @Plugins — no @MockFrontend indirection
        $themeConfig = new FrontendPluginConfiguration('TestTheme');
        $themeConfig->setIsTheme(true);
        $themeConfig->setStyleFiles(FileCollection::createFromArray(['@Plugins']));
        $themeConfig->setScriptFiles(new FileCollection());

        $plugin = $factory->createFromBundle($pluginBundle);

        $configCollection = new FrontendPluginConfigurationCollection([$themeConfig, $plugin]);

        $kernel = static::createStub(Kernel::class);
        $kernel->method('getBundles')->willReturn([
            'SimplePlugin' => $pluginBundle,
        ]);
        $kernel->method('getBundle')->willReturnMap([
            ['SimplePlugin', $pluginBundle],
        ]);

        $themeFilesystemResolver = new ThemeFilesystemResolver($kernel);
        $result = new ThemeFileResolver($themeFilesystemResolver)
            ->resolveStyleFiles($themeConfig, $configCollection, false);

        $paths = $result->getFilepaths();
        $pluginStyleIncluded = false;
        foreach ($paths as $path) {
            if (mb_stripos((string) $path, 'SimplePlugin') !== false) {
                $pluginStyleIncluded = true;
                break;
            }
        }

        static::assertTrue($pluginStyleIncluded, '@Plugins should include style files from non-theme plugins');
    }

    public function testDuplicateNamespaceReferenceIsExpandedOnlyOnce(): void
    {
        $config = new FrontendPluginConfiguration('TestTheme');
        // @FrontendBootstrap listed twice — base.scss should still appear exactly once
        $config->setStyleFiles(FileCollection::createFromArray(['@FrontendBootstrap', '@FrontendBootstrap']));
        $config->setScriptFiles(new FileCollection());

        $configCollection = new FrontendPluginConfigurationCollection([$config]);

        $themeFilesystemResolver = static::createStub(ThemeFilesystemResolver::class);
        $resolver = new ThemeFileResolver($themeFilesystemResolver);

        $result = $resolver->resolveStyleFiles($config, $configCollection, false);

        static::assertCount(1, $result, 'Duplicate @Namespace reference should be expanded only once');
    }

    public function testNamespaceReferenceWithoutSlashIsResolvedAsNamespace(): void
    {
        $themePluginBundle = new ThemeWithFrontendSkinScss();
        $frontendBundle = new MockFrontend();

        $factory = new FrontendPluginConfigurationFactory(
            static::createStub(KernelPluginLoader::class),
            new Filesystem(),
        );

        $config = $factory->createFromBundle($themePluginBundle);
        $config->setStyleFiles(FileCollection::createFromArray(['@MockFrontend']));
        $frontend = $factory->createFromBundle($frontendBundle);

        $configCollection = new FrontendPluginConfigurationCollection([$config, $frontend]);

        $kernel = static::createStub(Kernel::class);
        $kernel->method('getBundles')->willReturn([
            'ThemeWithFrontendSkinScss' => $themePluginBundle,
            'MockFrontend' => $frontendBundle,
        ]);
        $kernel->method('getBundle')->willReturnMap([
            ['ThemeWithFrontendSkinScss', $themePluginBundle],
            ['MockFrontend', $frontendBundle],
        ]);

        $resolver = new ThemeFileResolver(new ThemeFilesystemResolver($kernel));
        $result = $resolver->resolveStyleFiles($config, $configCollection, false);

        $paths = $result->getFilepaths();
        static::assertNotEmpty($paths);
        static::assertTrue(
            (bool) array_filter(
                $paths,
                static fn (?string $path): bool => \is_string($path) && str_contains($path, 'MockFrontend/Resources/app/frontend/src/scss/base.scss')
            )
        );
    }

    public function testNonNamespaceFilePathIsHandledAsDirectFileReference(): void
    {
        $config = new FrontendPluginConfiguration('TestTheme');
        $fileCollection = new FileCollection();
        $fileCollection->add(new File(__DIR__ . '/fixtures/MockFrontend/Resources/app/frontend/src/scss/base.scss'));
        $config->setStyleFiles($fileCollection);
        $config->setScriptFiles(new FileCollection());

        $resolver = new ThemeFileResolver(static::createStub(ThemeFilesystemResolver::class));
        $result = $resolver->resolveStyleFiles($config, new FrontendPluginConfigurationCollection([$config]), false);

        static::assertCount(1, $result);
        static::assertSame(
            __DIR__ . '/fixtures/MockFrontend/Resources/app/frontend/src/scss/base.scss',
            $result->first()?->getFilepath()
        );
    }
}

<?php declare(strict_types=1);

namespace Contena\Tests\DevOps\Core\Test;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;
use Contena\Core\DevOps\Test\AnnotationTagTester;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Kernel;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 */
class AnnotationTagTest extends TestCase
{
    /**
     * white list file path segments for ignored paths
     *
     * @var array<string>
     */
    private array $whiteList = [
        'vendor',
        'node_modules/',
        'Common/vendor/',
        'Recovery/vendor',
        'Core/DevOps/StaticAnalyze',
        'recovery/vendor',
        'storefront/vendor',
        // no need to check external js added as assets
        'storefront/dist/assets/js',
        // we cannot remove the method, because old migrations could still use it
        'Migration/MigrationStep.php',
        // example plugin
        'deprecation.plugin.js',
        // some eslint rules check for @deprecated and therefore produce false positives
        'administration/eslint-rules',
        // checks for deprecations too and annotation fails
        'DataAbstractionLayer/DefinitionValidator.php',
        // Test/AnnotationTagTest.php
        'Test/AnnotationTagTest.php',
        'Test/AnnotationTagTester.php',
        'Test/AnnotationTagTesterTest.php',
        // hook and service reference generator test fixtures intentionally omit annotations
        'unit/Core/DevOps/Docs/Script/_fixtures',
        // uses @experimental annotation check
        'Core/Framework/ApiRoutesHaveASchemaTest.php',
        // copies DBAL code that don't use our deprecation policies
        'Core/Framework/Adapter/Doctrine/Patch',
    ];

    private string $rootDir;

    private ?AnnotationTagTester $deprecationTagTester = null;

    protected function setUp(): void
    {
        $this->rootDir = $this->getPathForClass(Kernel::class);
    }

    public function testSourceFilesForWrongDeprecatedAnnotations(): void
    {
        $finder = new Finder();
        $finder->in([$this->rootDir, $this->rootDir . '/../tests'])
            ->files()
            ->name('*.php')
            ->name('*.js')
            ->name('*.ts')
            ->name('*.scss')
            ->name('*.html.twig')
            ->name('*.xsd')
            ->exclude('node_modules')
            ->contains(['@deprecated', '@experimental']);

        foreach ($this->whiteList as $path) {
            $finder->notPath($path);
        }

        $invalidFiles = [];

        foreach ($finder->getIterator() as $file) {
            $filePath = $file->getRealPath();
            $content = (string) file_get_contents($filePath);

            try {
                $this->getDeprecationTagTester()->validateDeprecatedAnnotations($content);
                $this->getDeprecationTagTester()->validateExperimentalAnnotations($content);
            } catch (\InvalidArgumentException $error) {
                $invalidFiles[$filePath] = $error->getMessage();
            }
        }

        static::assertEmpty($invalidFiles, print_r($invalidFiles, true));
    }

    public function testConfigFilesForWrongDeprecatedTags(): void
    {
        $finder = new Finder();
        $finder->in([$this->rootDir, $this->rootDir . '/../tests'])
            ->files()
            ->name('*.xml')
            ->exclude('node_modules')
            ->contains('<deprecated>');

        foreach ($this->whiteList as $path) {
            $finder->notPath($path);
        }

        $invalidFiles = [];

        foreach ($finder->getIterator() as $file) {
            $filePath = $file->getRealPath();
            $content = (string) file_get_contents($filePath);

            try {
                $this->getDeprecationTagTester()->validateDeprecationElements($content);
            } catch (\Throwable $error) {
                if ($error->getMessage() !== 'Deprecation tag is not found in the file.') {
                    $invalidFiles[$filePath] = $error->getMessage();
                }
            }
        }

        static::assertEmpty($invalidFiles, print_r($invalidFiles, true));
    }

    private function getPathForClass(string $className): string
    {
        $path = realpath(\dirname((string) KernelLifecycleManager::getClassLoader()->findFile($className)) . '/../');

        if ($path === false) {
            throw new \LogicException("could not locate filepath for class {$className}");
        }

        return $path;
    }

    private function getDeprecationTagTester(): AnnotationTagTester
    {
        if ($this->deprecationTagTester === null) {
            $this->deprecationTagTester = new AnnotationTagTester(
                $this->getContenaVersion(),
                '0.0'
            );
        }

        return $this->deprecationTagTester;
    }

    /**
     * can be overwritten with env variable VERSION
     */
    private function getContenaVersion(): string
    {
        $envVersion = $_SERVER['VERSION'] ?? $_SERVER['TAG'] ?? '';
        if (\is_string($envVersion) && $envVersion !== '') {
            $contenaVersion = $envVersion;
        } elseif (InstalledVersions::isInstalled('contena/platform')) {
            $contenaVersion = InstalledVersions::getVersion('contena/platform');
        } else {
            $contenaVersion = InstalledVersions::getVersion('contena/core');
        }
        $contenaVersion = ltrim((string) $contenaVersion, 'v ');

        if (!preg_match('/^\d+\.\d+[.-].*$/', $contenaVersion)) {
            // this will only check the syntax of the deprecated tags. The real test happens in the prod pipeline

            // The platform targets the unreleased 6.8 development line while
            // deprecation release checks still use the latest released line.
            $contenaVersion = '6.7.0';
        }

        return $contenaVersion;
    }
}

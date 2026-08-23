<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Plugin\Util;

use Composer\IO\NullIO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Plugin\Composer\PackageProvider;
use Contena\Core\Framework\Plugin\Exception\ExceptionCollection;
use Contena\Core\Framework\Plugin\Exception\PluginComposerJsonInvalidException;
use Contena\Core\Framework\Plugin\Struct\PluginFromFileSystemStruct;
use Contena\Core\Framework\Plugin\Util\PluginFinder;

/**
 * @internal
 */
#[CoversClass(PluginFinder::class)]
class PluginFinderTest extends TestCase
{
    public function testFailsOnMissingRootComposerFile(): void
    {
        $errors = new ExceptionCollection();
        new PluginFinder(new PackageProvider())->findPlugins(
            __DIR__,
            __DIR__ . '/../../../../../..',
            $errors,
            new NullIO()
        );

        static::assertInstanceOf(PluginComposerJsonInvalidException::class, $errors->first());
    }

    public function testLocalLoadsTheComposerJsonContents(): void
    {
        $plugins = new PluginFinder(new PackageProvider())->findPlugins(
            __DIR__ . '/_fixture/LocallyInstalledPlugins',
            __DIR__ . '/_fixture/ComposerProject',
            new ExceptionCollection(),
            new NullIO()
        );
        static::assertCount(2, $plugins);
        static::assertSame($plugins['Ct\Test']->getBaseClass(), 'Ct\Test');
    }

    /*
     * Referring to __DIR__ . '/_fixture/', you can see that we have the same plugin installed locally (residing inside
     * the directory for locally installed plugins) and via composer (residing in the vendor directory and being
     * registered in the installed.json). The Pluginfinder should always consider plugin definitions found via composer
     * over those found in the local directory.
     */
    public function testConsidersComposerInstalledPluginsOverLocalInstalledPlugins(): void
    {
        $plugins = new PluginFinder(new PackageProvider())->findPlugins(
            __DIR__ . '/_fixture/LocallyInstalledPlugins',
            __DIR__ . '/_fixture/ComposerProject',
            new ExceptionCollection(),
            new NullIO()
        );

        static::assertInstanceOf(PluginFromFileSystemStruct::class, $plugins['Ct\Test']);
        static::assertTrue($plugins['Ct\Test']->getManagedByComposer());
        // path is still local if it exists
        static::assertSame(__DIR__ . '/_fixture/LocallyInstalledPlugins/CtTest', $plugins['Ct\Test']->getPath());
        // version info is still from local, as that might be more up to date
        static::assertSame('v1.0.2', $plugins['Ct\Test']->getComposerPackage()->getPrettyVersion());
    }

    public function testComposerPackageFromPluginIsUsedIfNoLocalInstalledVersionExists(): void
    {
        $plugins = new PluginFinder(new PackageProvider())->findPlugins(
            __DIR__ . '/_fixture/LocallyInstalledPlugins',
            __DIR__ . '/_fixture/ComposerProject',
            new ExceptionCollection(),
            new NullIO()
        );

        static::assertInstanceOf(PluginFromFileSystemStruct::class, $plugins['Ct\Test2']);
        static::assertTrue($plugins['Ct\Test2']->getManagedByComposer());
        // path is still local if it exists
        static::assertSame(__DIR__ . '/_fixture/ComposerProject/vendor/ct/test2', $plugins['Ct\Test2']->getPath());
        // version info is still from local, as that might be more up to date
        static::assertSame('v2.0.1', $plugins['Ct\Test2']->getComposerPackage()->getPrettyVersion());
    }
}

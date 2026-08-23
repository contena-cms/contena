<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Contena\Framework\Plugin;

use Composer\IO\NullIO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Plugin\Composer\PackageProvider;
use Contena\Core\Framework\Plugin\Exception\ExceptionCollection;
use Contena\Core\Framework\Plugin\Util\PluginFinder;

/**
 * @internal
 */
#[CoversClass(PluginFinder::class)]
final class ContenaMinimalPluginContractTest extends TestCase
{
    #[TestDox('The non-distributable Contena fixture is discovered through the retained PluginFinder contract')]
    public function testMinimalFixtureCanBeDiscovered(): void
    {
        $projectRoot = \dirname(__DIR__, 5);
        $errors = new ExceptionCollection();
        $plugins = new PluginFinder(new PackageProvider())->findPlugins(
            $projectRoot . '/tests/fixtures/plugins',
            $projectRoot . '/tests/unit/Core/Framework/Plugin/Util/_fixture/ComposerProject',
            $errors,
            new NullIO(),
        );

        $plugin = $plugins['Contena\\Fixtures\\MinimalLifecyclePlugin\\MinimalLifecyclePlugin'] ?? null;

        static::assertNotNull($plugin);
        static::assertSame('contena/minimal-lifecycle-plugin', $plugin->getComposerPackage()->getPrettyName());
        static::assertSame('1.0.0', $plugin->getComposerPackage()->getPrettyVersion());
        static::assertFalse($plugin->getManagedByComposer());
        static::assertCount(0, $errors);
    }
}

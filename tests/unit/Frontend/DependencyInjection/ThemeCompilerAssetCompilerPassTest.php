<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Frontend\DependencyInjection\ThemeCompilerAssetCompilerPass;
use Contena\Frontend\Theme\ThemeCompiler;
use Symfony\Component\Asset\Package as AssetPackage;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(ThemeCompilerAssetCompilerPass::class)]
class ThemeCompilerAssetCompilerPassTest extends TestCase
{
    public function testInjectsContenaAssetReferencesIntoThemeCompilerWhenPresent(): void
    {
        $container = $this->createContainerWithAssets();

        $themeCompilerDefinition = new Definition(ThemeCompiler::class);
        $themeCompilerDefinition->setPublic(true);
        $container->setDefinition(ThemeCompiler::class, $themeCompilerDefinition);

        new ThemeCompilerAssetCompilerPass()->process($container);

        $argument = $container->getDefinition(ThemeCompiler::class)->getArgument('$packages');

        static::assertIsArray($argument);
        static::assertArrayHasKey('asset', $argument);
    }

    public function testIsNoOpWhenThemeCompilerIsAbsent(): void
    {
        $container = $this->createContainerWithAssets();

        static::assertFalse($container->hasDefinition(ThemeCompiler::class));

        new ThemeCompilerAssetCompilerPass()->process($container);

        static::assertFalse($container->hasDefinition(ThemeCompiler::class));
    }

    private function createContainerWithAssets(): ContainerBuilder
    {
        $container = new ContainerBuilder();

        $assetService = new Definition(Packages::class);
        $assetService->setPublic(true);
        $container->setDefinition('assets.packages', $assetService);

        $assetDefinition = new Definition(AssetPackage::class);
        $assetDefinition->addTag('contena.asset', ['asset' => 'asset']);
        $assetDefinition->setPublic(true);
        $container->setDefinition('my.asset.service', $assetDefinition);

        return $container;
    }
}

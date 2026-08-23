<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DependencyInjection\CompilerPass\ThemeAssetVersionStrategyCompilerPass;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(ThemeAssetVersionStrategyCompilerPass::class)]
class ThemeAssetVersionStrategyCompilerPassTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->setDefinition('contena.asset.theme.version_strategy', new Definition(\stdClass::class));
        $this->container->setDefinition('assets.empty_version_strategy', new Definition(EmptyVersionStrategy::class));
    }

    public function testDoesNothingWhenParameterAbsent(): void
    {
        new ThemeAssetVersionStrategyCompilerPass()->process($this->container);

        static::assertTrue($this->container->hasDefinition('contena.asset.theme.version_strategy'));
        static::assertFalse($this->container->hasAlias('contena.asset.theme.version_strategy'));
    }

    public function testDoesNothingWhenStrategyEnabled(): void
    {
        $this->container->setParameter('contena.filesystem.theme.use_last_modified_version_strategy', true);

        new ThemeAssetVersionStrategyCompilerPass()->process($this->container);

        static::assertTrue($this->container->hasDefinition('contena.asset.theme.version_strategy'));
        static::assertFalse($this->container->hasAlias('contena.asset.theme.version_strategy'));
    }

    public function testReplacesDefinitionWithAliasWhenStrategyDisabled(): void
    {
        $this->container->setParameter('contena.filesystem.theme.use_last_modified_version_strategy', false);

        new ThemeAssetVersionStrategyCompilerPass()->process($this->container);

        static::assertFalse($this->container->hasDefinition('contena.asset.theme.version_strategy'));
        static::assertTrue($this->container->hasAlias('contena.asset.theme.version_strategy'));
        static::assertSame('assets.empty_version_strategy', (string) $this->container->getAlias('contena.asset.theme.version_strategy'));
    }
}

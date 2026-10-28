<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Theme\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Theme\ThemeRuntimeConfig;
use Contena\Storefront\Theme\ThemeRuntimeConfigService;
use Contena\Storefront\Theme\Twig\ThemeInheritanceBuilder;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeInheritanceBuilder::class)]
class ThemeInheritanceBuilderTest extends TestCase
{

    protected function setUp(): void
    {
        $runtimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $runtimeConfigService
            ->method('getActiveThemeNames')
            ->willReturn(['Storefront']);

        $runtimeConfigService
            ->expects($this->once())
            ->method('getRuntimeConfigByName')
            ->willReturn(ThemeRuntimeConfig::fromArray([
                'themeId' => 'theme-db-id',
                'technicalName' => 'Storefront',
            ]));

        $this->builder = new ThemeInheritanceBuilder($runtimeConfigService);
    }

    public function testBuildPreservesThePluginOrder(): void
    {
        $result = $this->builder->build([
            'ExtensionPlugin' => 0,
            'BasePlugin' => 0,
            'Storefront' => 0,
        ], [
            'Storefront' => true,
        ]);

        static::assertSame([
            'ExtensionPlugin' => 0,
            'BasePlugin' => 0,
            'Storefront' => 0,
        ], $result);
    }

    public function testSortBundlesByPriority(): void
    {
        $result = $this->builder->build([
            'Profiling' => -2,
            'Elasticsearch' => -1,
            'Administration' => -1,
            'Framework' => -1,
            'ExtensionPlugin' => 0,
            'Storefront' => 0,
        ], [
            'Storefront' => true,
        ]);

        static::assertSame([
            'ExtensionPlugin' => 0,
            'Elasticsearch' => -1,
            'Administration' => -1,
            'Framework' => -1,
            'Profiling' => -2,
            'Storefront' => 0,
        ], $result);
    }
}

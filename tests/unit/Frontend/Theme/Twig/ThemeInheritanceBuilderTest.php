<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Frontend\Theme\ThemeRuntimeConfig;
use Contena\Frontend\Theme\ThemeRuntimeConfigService;
use Contena\Frontend\Theme\Twig\ThemeInheritanceBuilder;

/**
 * @internal
 */
#[CoversClass(ThemeInheritanceBuilder::class)]
class ThemeInheritanceBuilderTest extends TestCase
{
    private ThemeInheritanceBuilder $builder;

    protected function setUp(): void
    {
        $runtimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);
        $runtimeConfigService
            ->method('getActiveThemeNames')
            ->willReturn(['Frontend']);

        $runtimeConfigService
            ->expects($this->once())
            ->method('getRuntimeConfigByName')
            ->willReturn(ThemeRuntimeConfig::fromArray([
                'themeId' => 'theme-db-id',
                'technicalName' => 'Frontend',
            ]));

        $this->builder = new ThemeInheritanceBuilder($runtimeConfigService);
    }

    public function testBuildPreservesThePluginOrder(): void
    {
        $result = $this->builder->build([
            'ExtensionPlugin' => 0,
            'BasePlugin' => 0,
            'Frontend' => 0,
        ], [
            'Frontend' => true,
        ]);

        static::assertSame([
            'ExtensionPlugin' => 0,
            'BasePlugin' => 0,
            'Frontend' => 0,
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
            'Frontend' => 0,
        ], [
            'Frontend' => true,
        ]);

        static::assertSame([
            'ExtensionPlugin' => 0,
            'Elasticsearch' => -1,
            'Administration' => -1,
            'Framework' => -1,
            'Profiling' => -2,
            'Frontend' => 0,
        ], $result);
    }
}

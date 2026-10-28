<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Storefront\Theme;

use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Storefront\Theme\StorefrontPluginRegistry;

/**
 * @internal
 */
#[Package('discovery')]
class StorefrontPluginRegistryTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    public function testConfigIsAddedIfItsATheme(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps/SwagTheme');

        $registry = static::getContainer()
            ->get(StorefrontPluginRegistry::class);

        static::assertInstanceOf(
            $registry->getConfigurations()->getByTechnicalName('SwagTheme')
        );
    }

    public function testConfigIsNotAddedIfAppIsNotActive(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps/SwagTheme', false);

        $registry = static::getContainer()
            ->get(StorefrontPluginRegistry::class);

        static::assertNull(
            $registry->getConfigurations()->getByTechnicalName('SwagTheme')
        );
    }

    public function testConfigIsAddedIfHasResourcesToCompile(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps/SwagNoThemeCustomCss');

        $registry = static::getContainer()
            ->get(StorefrontPluginRegistry::class);

        static::assertInstanceOf(
            $registry->getConfigurations()->getByTechnicalName('SwagNoThemeCustomCss')
        );
    }

    public function testConfigIsNotAddedButIdentifiedAsNotThemeIfItsNotATheme(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/fixtures/Apps/SwagNoThemeNoCss');

        $registry = static::getContainer()
            ->get(StorefrontPluginRegistry::class);

        static::assertInstanceOf(
            $registry->getConfigurations()->getByTechnicalName('SwagNoThemeNoCss')
        );

        static::assertNull(
            $registry->getConfigurations()->getThemes()->getByTechnicalName('SwagNoThemeNoCss')
        );
    }
}

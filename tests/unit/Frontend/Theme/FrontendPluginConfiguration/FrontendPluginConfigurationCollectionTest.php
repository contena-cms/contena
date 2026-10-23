<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\FrontendPluginConfiguration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;

/**
 * @internal
 */
#[CoversClass(FrontendPluginConfigurationCollection::class)]
class FrontendPluginConfigurationCollectionTest extends TestCase
{
    public function testElementsAreKeyedByTechnicalName(): void
    {
        $theme = self::configuration('SwagTheme', isTheme: true);

        $collection = new FrontendPluginConfigurationCollection([$theme]);

        static::assertSame($theme, $collection->get('SwagTheme'));
    }

    public function testAddKeysTheElementByTechnicalName(): void
    {
        $plugin = self::configuration('SwagPlugin', isTheme: false);

        $collection = new FrontendPluginConfigurationCollection();
        $collection->add($plugin);

        static::assertSame($plugin, $collection->get('SwagPlugin'));
    }

    public function testGetByTechnicalName(): void
    {
        $theme = self::configuration('SwagTheme', isTheme: true);
        $collection = new FrontendPluginConfigurationCollection([$theme]);

        static::assertSame($theme, $collection->getByTechnicalName('SwagTheme'));
        static::assertNull($collection->getByTechnicalName('Unknown'));
    }

    public function testGetThemesReturnsOnlyThemes(): void
    {
        $theme = self::configuration('SwagTheme', isTheme: true);
        $plugin = self::configuration('SwagPlugin', isTheme: false);

        $collection = new FrontendPluginConfigurationCollection([$theme, $plugin]);

        static::assertSame([$theme], array_values($collection->getThemes()->getElements()));
        static::assertSame([$plugin], array_values($collection->getNoneThemes()->getElements()));
    }

    private static function configuration(string $technicalName, bool $isTheme): FrontendPluginConfiguration
    {
        $configuration = new FrontendPluginConfiguration($technicalName);
        $configuration->setIsTheme($isTheme);

        return $configuration;
    }
}

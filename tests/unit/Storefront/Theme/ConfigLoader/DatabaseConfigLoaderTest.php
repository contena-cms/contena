<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Theme\ConfigLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Storefront\Theme\ConfigLoader\DatabaseConfigLoader;
use Contena\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Contena\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Contena\Storefront\Theme\StorefrontPluginRegistry;
use Contena\Storefront\Theme\ThemeCollection;
use Contena\Storefront\Theme\ThemeDefinition;
use Contena\Storefront\Theme\ThemeEntity;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(DatabaseConfigLoader::class)]
class DatabaseConfigLoaderTest extends TestCase
{
    /**
     * @var StaticEntityRepository<ThemeCollection>
     */
    private StaticEntityRepository $themeRepository;

    private MockObject&StorefrontPluginRegistry $storefrontPluginRegistry;

    /**
     * @var StaticEntityRepository<MediaCollection>
     */
    private StaticEntityRepository $mediaRepository;


    protected function setUp(): void
    {
        parent::setUp();

        $this->themeRepository = new StaticEntityRepository([], new ThemeDefinition());
        $this->storefrontPluginRegistry = $this->createMock(StorefrontPluginRegistry::class);
        $this->mediaRepository = new StaticEntityRepository([new MediaCollection([])], new MediaDefinition());

        $this->databaseConfigLoader = new DatabaseConfigLoader(
            $this->themeRepository,
            $this->storefrontPluginRegistry,
            $this->mediaRepository
        );
    }

    public function testItLoadsStorefrontPluginConfiguration(): void
    {
        $themeId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $theme = new ThemeEntity();
        $theme->setId($themeId);
        $theme->setName('FooBar');
        $theme->setTechnicalName('FooBar');
        $theme->setActive(true);
        $theme->setBaseConfig([
            'foo' => [
                'type' => 'media',
                'value' => 'bar',
            ],
            'bar' => [
                'type' => 'media',
                'value' => 'foo',
            ],
        ]);
        $theme->setConfigValues([
            'foo' => [
                'type' => 'media',
                'value' => null,
            ],
        ]);

        $baseTheme = new ThemeEntity();
        $baseTheme->setId(Uuid::randomHex());
        $baseTheme->setTechnicalName(StorefrontPluginRegistry::BASE_THEME_NAME);

        $this->themeRepository->searches = [new ThemeCollection([$theme]), new ThemeCollection([$theme, $baseTheme])];

        $configuration = new StorefrontPluginConfiguration('FooBar');
        $configuration->setThemeConfig($theme->getBaseConfig());

        $this->storefrontPluginRegistry
            ->expects($this->exactly(3))
            ->method('getConfigurations')
            ->willReturn(new StorefrontPluginConfigurationCollection([$configuration]));

        $config = $this->databaseConfigLoader->load($themeId, $context);

        static::assertNotNull($config->getThemeConfig());
        static::assertArrayHasKey('foo', $config->getThemeConfig());
        static::assertNull($config->getThemeConfig()['foo']['value']);
        static::assertArrayHasKey('bar', $config->getThemeConfig());
        static::assertSame('foo', $config->getThemeConfig()['bar']['value']);
    }
}

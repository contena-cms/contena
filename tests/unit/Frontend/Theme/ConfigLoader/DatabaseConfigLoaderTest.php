<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Theme\ConfigLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Media\MediaCollection;
use Contena\Core\Content\Media\MediaDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Frontend\Theme\ConfigLoader\DatabaseConfigLoader;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfiguration;
use Contena\Frontend\Theme\FrontendPluginConfiguration\FrontendPluginConfigurationCollection;
use Contena\Frontend\Theme\FrontendPluginRegistry;
use Contena\Frontend\Theme\ThemeCollection;
use Contena\Frontend\Theme\ThemeDefinition;
use Contena\Frontend\Theme\ThemeEntity;

/**
 * @internal
 */
#[CoversClass(DatabaseConfigLoader::class)]
class DatabaseConfigLoaderTest extends TestCase
{
    /**
     * @var StaticEntityRepository<ThemeCollection>
     */
    private StaticEntityRepository $themeRepository;

    private MockObject&FrontendPluginRegistry $frontendPluginRegistry;

    /**
     * @var StaticEntityRepository<MediaCollection>
     */
    private StaticEntityRepository $mediaRepository;

    private DatabaseConfigLoader $databaseConfigLoader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->themeRepository = new StaticEntityRepository([], new ThemeDefinition());
        $this->frontendPluginRegistry = $this->createMock(FrontendPluginRegistry::class);
        $this->mediaRepository = new StaticEntityRepository([new MediaCollection([])], new MediaDefinition());

        $this->databaseConfigLoader = new DatabaseConfigLoader(
            $this->themeRepository,
            $this->frontendPluginRegistry,
            $this->mediaRepository
        );
    }

    public function testItLoadsFrontendPluginConfiguration(): void
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
        $baseTheme->setTechnicalName(FrontendPluginRegistry::BASE_THEME_NAME);

        $this->themeRepository->searches = [new ThemeCollection([$theme]), new ThemeCollection([$theme, $baseTheme])];

        $configuration = new FrontendPluginConfiguration('FooBar');
        $configuration->setThemeConfig($theme->getBaseConfig());

        $this->frontendPluginRegistry
            ->expects($this->exactly(3))
            ->method('getConfigurations')
            ->willReturn(new FrontendPluginConfigurationCollection([$configuration]));

        $config = $this->databaseConfigLoader->load($themeId, $context);

        static::assertNotNull($config->getThemeConfig());
        static::assertArrayHasKey('foo', $config->getThemeConfig());
        static::assertNull($config->getThemeConfig()['foo']['value']);
        static::assertArrayHasKey('bar', $config->getThemeConfig());
        static::assertSame('foo', $config->getThemeConfig()['bar']['value']);
    }
}

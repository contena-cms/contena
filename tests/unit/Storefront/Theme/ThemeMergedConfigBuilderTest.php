<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Theme;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Storefront\Theme\Exception\ThemeException;
use Contena\Storefront\Theme\StorefrontPluginRegistry;
use Contena\Storefront\Theme\ThemeCollection;
use Contena\Storefront\Theme\ThemeMergedConfigBuilder;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThemeMergedConfigBuilder::class)]
class ThemeMergedConfigBuilderTest extends TestCase
{
    use EnvTestBehaviour;

    private StorefrontPluginRegistry&Stub $storefrontPluginRegistryMock;

    /**
     * @var EntityRepository<ThemeCollection>&Stub
     */
    private EntityRepository&Stub $themeRepositoryMock;


    private Context $context;

    protected function setUp(): void
    {
        $this->storefrontPluginRegistryMock = static::createStub(StorefrontPluginRegistry::class);
        $this->themeRepositoryMock = static::createStub(EntityRepository::class);

        $this->context = Context::createDefaultContext();

        $this->mergedConfigBuilder = new ThemeMergedConfigBuilder(
            $this->storefrontPluginRegistryMock,
            $this->themeRepositoryMock,
        );
    }

    public function testGetPlainThemeConfigurationNoTheme(): void
    {
        $themeId = Uuid::randomHex();

        $this->themeRepositoryMock->method('search')->willReturn(
            new EntitySearchResult(
                'theme',
                1,
                new ThemeCollection(
                    [
                    ]
                ),
                null,
                new Criteria(),
                $this->context
            )
        );

        $this->expectExceptionObject(ThemeException::couldNotFindThemeById($themeId));

        $this->mergedConfigBuilder->getPlainThemeConfiguration($themeId, $this->context);
    }






}

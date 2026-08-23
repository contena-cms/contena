<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Language\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Contena\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Contena\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\Channel\AbstractLanguageRoute;
use Contena\Core\System\Language\Channel\LanguageRouteResponse;
use Contena\Core\System\Language\ContentSystem\DataLoader\LanguageDataLoader;
use Contena\Core\System\Language\ContentSystem\DataLoader\LanguageLoaderConfig;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(LanguageDataLoader::class)]
class LanguageDataLoaderTest extends TestCase
{
    private AbstractLanguageRoute&Stub $languageRoute;

    private LanguageDataLoader $dataLoader;

    protected function setUp(): void
    {
        $this->languageRoute = static::createStub(AbstractLanguageRoute::class);
        $this->dataLoader = new LanguageDataLoader($this->languageRoute);
    }

    #[TestDox('returns language source type identifier')]
    public function testGetRequirementTypeReturnsLanguageString(): void
    {
        static::assertSame('language', LanguageDataLoader::getRequirementType());
    }

    #[TestDox('declares LanguageCollection as its single producible type')]
    public function testProducibleTypesDeclaresExtendsType(): void
    {
        $capabilities = $this->dataLoader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(LanguageCollection::class, $capabilities[0]->producedType);
        static::assertSame([], $capabilities[0]->genericParameters);
        static::assertSame([], $capabilities[0]->configTemplate);
    }

    #[TestDox('loads languages and returns cachedExternally result with correct request, context and empty criteria')]
    public function testLoadWithDefaultConfig(): void
    {
        $languages = new LanguageCollection();
        $response = $this->createLanguageRouteResponse($languages);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test');
        $config = new LanguageLoaderConfig();
        $requirement = new DataRequirement('languages', 'language', $config);
        $context = Generator::generateChannelContext();
        $request = new Request();

        $this->languageRoute
            ->method('load')
            ->willReturn($response);

        $result = $this->dataLoader->load($element, $requirement, $context, $request);

        static::assertTrue($result->hasData());
        static::assertSame($languages, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('adds associations from LanguageLoaderConfig to criteria')]
    public function testLoadAddsAssociationsFromConfigToCriteria(): void
    {
        $languages = new LanguageCollection();
        $response = $this->createLanguageRouteResponse($languages);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test');
        $config = new LanguageLoaderConfig(associations: ['locale', 'translationCode']);
        $requirement = new DataRequirement('languages', 'language', $config);
        $context = Generator::generateChannelContext();
        $request = new Request();

        $languageRoute = $this->createMock(AbstractLanguageRoute::class);
        $languageRoute
            ->expects($this->once())
            ->method('load')
            ->with(
                static::anything(),
                static::anything(),
                static::callback(function (Criteria $criteria): bool {
                    static::assertContains('locale', array_keys($criteria->getAssociations()));
                    static::assertContains('translationCode', array_keys($criteria->getAssociations()));

                    return true;
                })
            )
            ->willReturn($response);

        $dataLoader = new LanguageDataLoader($languageRoute);
        $dataLoader->load($element, $requirement, $context, $request);
    }

    #[TestDox('loads languages without associations when config is not a LanguageLoaderConfig instance')]
    public function testLoadWithWrongConfigTypeSkipsAssociations(): void
    {
        $languages = new LanguageCollection();
        $response = $this->createLanguageRouteResponse($languages);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test');
        $wrongConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $requirement = new DataRequirement('languages', 'language', $wrongConfig);
        $context = Generator::generateChannelContext();
        $request = new Request();

        $this->languageRoute
            ->method('load')
            ->willReturn($response);

        $result = $this->dataLoader->load($element, $requirement, $context, $request);

        static::assertTrue($result->hasData());
        static::assertSame($languages, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    private function createLanguageRouteResponse(LanguageCollection $languages): LanguageRouteResponse
    {
        $response = static::createStub(LanguageRouteResponse::class);
        $response->method('getLanguages')->willReturn($languages);

        return $response;
    }
}

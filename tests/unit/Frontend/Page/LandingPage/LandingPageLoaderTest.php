<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\LandingPage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\LandingPage\Channel\AbstractLandingPageRoute;
use Contena\Core\Content\LandingPage\Channel\LandingPageRouteResponse;
use Contena\Core\Content\LandingPage\LandingPageEntity;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Generator;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Contena\Frontend\Page\LandingPage\LandingPageLoader;
use Contena\Frontend\Page\MetaInformation;
use Contena\Frontend\Page\Page;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(LandingPageLoader::class)]
class LandingPageLoaderTest extends TestCase
{
    public function testNoLandingPageIdException(): void
    {
        $landingPageRoute = $this->createMock(AbstractLandingPageRoute::class);
        $landingPageRoute->expects($this->never())->method('load');

        $landingPageLoader = new LandingPageLoader(
            static::createStub(GenericPageLoaderInterface::class),
            $landingPageRoute,
            static::createStub(EventDispatcherInterface::class),
        );

        $request = new Request([], [], []);

        static::expectExceptionObject(RoutingException::missingRequestParameter('landingPageId', '/landingPageId'));
        $landingPageLoader->load($request, Generator::generateChannelContext());
    }

    public function testItLoads(): void
    {
        $landingPageId = Uuid::randomHex();
        $landingPage = new LandingPageEntity();
        $landingPage->setId($landingPageId);

        $request = new Request([], [], ['landingPageId' => $landingPageId]);
        $page = $this->createLoader($landingPage)->load($request, Generator::generateChannelContext());

        static::assertSame($landingPage, $page->getLandingPage());
        static::assertSame($landingPageId, $page->getNavigationId());
    }

    public function testItLoadsProperPageMetaInformation(): void
    {
        $landingPage = $this->landingPageWithTranslation([
            'name' => 'TEST_NAME',
            'metaTitle' => 'TEST_META_TITLE',
            'metaDescription' => 'TEST_META_DESCRIPTION',
            'keywords' => 'TEST_KEYWORDS',
        ]);

        $request = new Request([], [], ['landingPageId' => $landingPage->getId()]);
        $metaInformation = $this->createLoader($landingPage)
            ->load($request, Generator::generateChannelContext())
            ->getMetaInformation();

        static::assertInstanceOf(MetaInformation::class, $metaInformation);
        static::assertSame('TEST_META_TITLE', $metaInformation->getMetaTitle());
        static::assertSame('TEST_META_DESCRIPTION', $metaInformation->getMetaDescription());
        static::assertSame('TEST_KEYWORDS', $metaInformation->getMetaKeywords());
    }

    public function testItLoadsProperPageMetaInformationWithNameOnly(): void
    {
        $landingPage = $this->landingPageWithTranslation(['name' => 'TEST_NAME']);

        $request = new Request([], [], ['landingPageId' => $landingPage->getId()]);
        $metaInformation = $this->createLoader($landingPage)
            ->load($request, Generator::generateChannelContext())
            ->getMetaInformation();

        static::assertInstanceOf(MetaInformation::class, $metaInformation);
        static::assertSame('TEST_NAME', $metaInformation->getMetaTitle());
        static::assertSame('', $metaInformation->getMetaDescription());
        static::assertSame('', $metaInformation->getMetaKeywords());
    }

    private function createLoader(LandingPageEntity $landingPage): LandingPageLoader
    {
        $genericPageLoader = static::createStub(GenericPageLoaderInterface::class);
        $genericPageLoader->method('load')->willReturn(new Page());

        $landingPageRoute = static::createStub(AbstractLandingPageRoute::class);
        $landingPageRoute->method('load')->willReturn(new LandingPageRouteResponse($landingPage));

        return new LandingPageLoader(
            $genericPageLoader,
            $landingPageRoute,
            static::createStub(EventDispatcherInterface::class),
        );
    }

    /**
     * @param array<string, string> $translated
     */
    private function landingPageWithTranslation(array $translated): LandingPageEntity
    {
        $landingPage = new LandingPageEntity();
        $landingPage->setId(Uuid::randomHex());
        $landingPage->setTranslated($translated);
        $landingPage->setName('INCORRECT_NAME');

        return $landingPage;
    }
}

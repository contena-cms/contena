<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\LandingPage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Contena\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Contena\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Contena\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\Content\Cms\Exception\PageNotFoundException;
use Contena\Core\Content\LandingPage\LandingPageEntity;
use Contena\Core\Content\LandingPage\LandingPageException;
use Contena\Core\Content\LandingPage\Channel\LandingPageRoute;
use Contena\Core\Content\LandingPage\Channel\LandingPageRouteResponse;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Generator;
use Contena\Frontend\Page\GenericPageLoader;
use Contena\Frontend\Page\LandingPage\LandingPageLoader;
use Contena\Frontend\Page\MetaInformation;
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
        $landingPageRouteMock = $this->createMock(LandingPageRoute::class);
        $landingPageRouteMock->expects($this->never())->method('load');

        $landingPageLoader = new LandingPageLoader(
            static::createStub(GenericPageLoader::class),
            $landingPageRouteMock,
            static::createStub(EventDispatcherInterface::class)
        );

        $request = new Request([], [], []);
        $channelContext = $this->getChannelContext();

        $this->expectExceptionObject(RoutingException::missingRequestParameter('landingPageId', '/landingPageId'));
        $landingPageLoader->load($request, $channelContext);
    }

    public function testNoLandingPageException(): void
    {
        $landingPageRouteMock = $this->createMock(LandingPageRoute::class);
        $landingPageRouteMock->expects($this->once())->method('load');

        $landingPageLoader = new LandingPageLoader(
            static::createStub(GenericPageLoader::class),
            $landingPageRouteMock,
            static::createStub(EventDispatcherInterface::class)
        );

        $landingPageId = Uuid::randomHex();
        $request = new Request([], [], ['landingPageId' => $landingPageId]);
        $channelContext = $this->getChannelContext();

        $expectedException = LandingPageException::notFound($landingPageId);

        // @deprecated tag:v6.8.0 - remove this if block
        if (!Feature::isActive('v6.8.0.0')) {
            $expectedException = new PageNotFoundException($landingPageId);
        }

        $this->expectExceptionObject($expectedException);
        $landingPageLoader->load($request, $channelContext);
    }

    public function testItLoads(): void
    {
        $blogId = Uuid::randomHex();
        $landingPageId = Uuid::randomHex();
        $request = new Request([], [], ['landingPageId' => $landingPageId]);
        $channelContext = $this->getChannelContext();

        $blog = $this->getBlog($blogId);
        $cmsPage = $this->getCmsPage($blog);

        $landingPageLoader = $this->getLandingPageLoaderWithBlog($landingPageId, $cmsPage, $request, $channelContext);

        $page = $landingPageLoader->load($request, $channelContext);

        $cmsPageLoaded = $page->getLandingPage();
        static::assertNotNull($cmsPageLoaded);
        static::assertSame($cmsPage, $cmsPageLoaded->getCmsPage());
        static::assertSame($landingPageId, $page->getNavigationId());
    }

    public function testItLoadsProperPageMetaInformation(): void
    {
        $landingPageId = Uuid::randomHex();
        $request = new Request([], [], ['landingPageId' => $landingPageId]);
        $channelContext = $this->getChannelContext();

        $translated = [
            'name' => 'TEST_NAME',
            'metaTitle' => 'TEST_META_TITLE',
            'metaDescription' => 'TEST_META_DESCRIPTION',
            'keywords' => 'TEST_KEYWORDS',
        ];

        $expected = [
            'metaTitle' => $translated['metaTitle'],
            'metaDescription' => $translated['metaDescription'],
            'metaKeywords' => $translated['keywords'],
        ];

        $landingPageLoader = $this->getLandingPageLoaderWithTranslated($landingPageId, $translated, $request, $channelContext);

        $metaInformation = $landingPageLoader->load($request, $channelContext)->getMetaInformation();

        static::assertInstanceOf(MetaInformation::class, $metaInformation);
        static::assertSame($metaInformation->getMetaTitle(), $expected['metaTitle']);
        static::assertSame($metaInformation->getMetaDescription(), $expected['metaDescription']);
        static::assertSame($metaInformation->getMetaKeywords(), $expected['metaKeywords']);
    }

    public function testItLoadsProperPageMetaInformationWithNameOnly(): void
    {
        $landingPageId = Uuid::randomHex();
        $request = new Request([], [], ['landingPageId' => $landingPageId]);
        $channelContext = $this->getChannelContext();

        $translated = [
            'name' => 'TEST_NAME',
        ];

        $expected = [
            'metaTitle' => $translated['name'],
            'metaDescription' => '',
            'metaKeywords' => '',
        ];

        $landingPageLoader = $this->getLandingPageLoaderWithTranslated($landingPageId, $translated, $request, $channelContext);

        $metaInformation = $landingPageLoader->load($request, $channelContext)->getMetaInformation();

        static::assertInstanceOf(MetaInformation::class, $metaInformation);
        static::assertSame($metaInformation->getMetaTitle(), $expected['metaTitle']);
        static::assertSame($metaInformation->getMetaDescription(), $expected['metaDescription']);
        static::assertSame($metaInformation->getMetaKeywords(), $expected['metaKeywords']);
    }

    private function getLandingPageLoaderWithBlog(
        string $landingPageId,
        CmsPageEntity $cmsPage,
        Request $request,
        ChannelContext $channelContext
    ): LandingPageLoader {
        $landingPage = new LandingPageEntity();
        $landingPage->setId($landingPageId);
        $landingPage->setCmsPage($cmsPage);

        return new LandingPageLoader(
            static::createStub(GenericPageLoader::class),
            $this->createLandingPageRoute($landingPage, $request, $channelContext),
            static::createStub(EventDispatcherInterface::class)
        );
    }

    /**
     * @param array<string> $translated
     */
    private function getLandingPageLoaderWithTranslated(
        string $landingPageId,
        array $translated,
        Request $request,
        ChannelContext $channelContext
    ): LandingPageLoader {
        $blogId = Uuid::randomHex();
        $blog = $this->getBlog($blogId);
        $cmsPage = $this->getCmsPage($blog);

        $landingPage = new LandingPageEntity();
        $landingPage->setId($landingPageId);
        $landingPage->setCmsPage($cmsPage);
        $landingPage->setTranslated($translated);
        $landingPage->setName('INCORRECT_NAME');

        return new LandingPageLoader(
            static::createStub(GenericPageLoader::class),
            $this->createLandingPageRoute($landingPage, $request, $channelContext),
            static::createStub(EventDispatcherInterface::class)
        );
    }

    private function createLandingPageRoute(
        LandingPageEntity $landingPage,
        Request $expectedRequest,
        ChannelContext $expectedContext
    ): LandingPageRoute {
        $landingPageRoute = $this->createMock(LandingPageRoute::class);
        $landingPageRoute
            ->expects($this->once())
            ->method('load')
            ->willReturnCallback(static function (
                string $landingPageId,
                Request $request,
                ChannelContext $context
            ) use (
                $landingPage,
                $expectedRequest,
                $expectedContext
            ): LandingPageRouteResponse {
                static::assertSame($landingPage->getId(), $landingPageId);
                static::assertSame($expectedRequest, $request);
                static::assertSame($expectedContext, $context);

                return new LandingPageRouteResponse($landingPage);
            });

        return $landingPageRoute;
    }

    private function getBlog(string $blogId): ChannelBlogEntity
    {
        $blog = new ChannelBlogEntity();
        $blog->setId($blogId);

        return $blog;
    }

    private function getChannelContext(): ChannelContext
    {
        $channelEntity = new ChannelEntity();
        $channelEntity->setId('channelId');

        return Generator::generateChannelContext(
            channel: $channelEntity,
        );
    }

    private function getCmsPage(ChannelBlogEntity $blogEntity): CmsPageEntity
    {
        $cmsPageEntity = new CmsPageEntity();

        $cmsSectionEntity = new CmsSectionEntity();
        $cmsSectionEntity->setId(Uuid::randomHex());

        $cmsBlockEntity = new CmsBlockEntity();
        $cmsBlockEntity->setId(Uuid::randomHex());

        $cmsSlotEntity = new CmsSlotEntity();
        $cmsSlotEntity->setId(Uuid::randomHex());
        $cmsSlotEntity->setSlot(json_encode($blogEntity->getTranslated(), \JSON_THROW_ON_ERROR));

        $cmsBlockEntity->setSlots(new CmsSlotCollection([$cmsSlotEntity]));
        $cmsSectionEntity->setBlocks(new CmsBlockCollection([$cmsBlockEntity]));
        $cmsPageEntity->setSections(new CmsSectionCollection([$cmsSectionEntity]));

        return $cmsPageEntity;
    }
}

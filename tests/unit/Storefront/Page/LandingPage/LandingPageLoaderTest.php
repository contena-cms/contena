<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Page\LandingPage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Cms\Exception\PageNotFoundException;
use Contena\Core\Content\LandingPage\LandingPageException;
use Contena\Core\Content\LandingPage\SalesChannel\LandingPageRoute;
use Contena\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\SalesChannel\SalesChannelEntity;
use Contena\Core\Test\Generator;
use Contena\Storefront\Page\GenericPageLoader;
use Contena\Storefront\Page\LandingPage\LandingPageLoader;
use Contena\Storefront\Page\MetaInformation;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
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
        $salesChannelContext = $this->getSalesChannelContext();

        $this->expectExceptionObject(RoutingException::missingRequestParameter('landingPageId', '/landingPageId'));
        $landingPageLoader->load($request, $salesChannelContext);
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
        $salesChannelContext = $this->getSalesChannelContext();

        $expectedException = LandingPageException::notFound($landingPageId);

        // @deprecated tag:v6.8.0 - remove this if block
        if (!Feature::isActive('v6.8.0.0')) {
            $expectedException = new PageNotFoundException($landingPageId);
        }

        $this->expectExceptionObject($expectedException);
        $landingPageLoader->load($request, $salesChannelContext);
    }

    public function testItLoads(): void
    {
        $productId = Uuid::randomHex();
        $landingPageId = Uuid::randomHex();
        $request = new Request([], [], ['landingPageId' => $landingPageId]);
        $salesChannelContext = $this->getSalesChannelContext();

        $product = $this->getProduct($productId);
        $cmsPage = $this->getCmsPage($product);

        $landingPageLoader = $this->getLandingPageLoaderWithProduct($landingPageId, $cmsPage, $request, $salesChannelContext);

        $page = $landingPageLoader->load($request, $salesChannelContext);

        $cmsPageLoaded = $page->getLandingPage();
        static::assertNotNull($cmsPageLoaded);
        static::assertSame($cmsPage, $cmsPageLoaded->getCmsPage());
        static::assertSame($landingPageId, $page->getNavigationId());
    }

    public function testItLoadsProperPageMetaInformation(): void
    {
        $landingPageId = Uuid::randomHex();
        $request = new Request([], [], ['landingPageId' => $landingPageId]);
        $salesChannelContext = $this->getSalesChannelContext();

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

        $landingPageLoader = $this->getLandingPageLoaderWithTranslated($landingPageId, $translated, $request, $salesChannelContext);

        $metaInformation = $landingPageLoader->load($request, $salesChannelContext)->getMetaInformation();

        static::assertInstanceOf(MetaInformation::class, $metaInformation);
        static::assertSame($metaInformation->getMetaTitle(), $expected['metaTitle']);
        static::assertSame($metaInformation->getMetaDescription(), $expected['metaDescription']);
        static::assertSame($metaInformation->getMetaKeywords(), $expected['metaKeywords']);
    }

    public function testItLoadsProperPageMetaInformationWithNameOnly(): void
    {
        $landingPageId = Uuid::randomHex();
        $request = new Request([], [], ['landingPageId' => $landingPageId]);
        $salesChannelContext = $this->getSalesChannelContext();

        $translated = [
            'name' => 'TEST_NAME',
        ];

        $expected = [
            'metaTitle' => $translated['name'],
            'metaDescription' => '',
            'metaKeywords' => '',
        ];

        $landingPageLoader = $this->getLandingPageLoaderWithTranslated($landingPageId, $translated, $request, $salesChannelContext);

        $metaInformation = $landingPageLoader->load($request, $salesChannelContext)->getMetaInformation();

        static::assertInstanceOf(MetaInformation::class, $metaInformation);
        static::assertSame($metaInformation->getMetaTitle(), $expected['metaTitle']);
        static::assertSame($metaInformation->getMetaDescription(), $expected['metaDescription']);
        static::assertSame($metaInformation->getMetaKeywords(), $expected['metaKeywords']);
    }




    private function getProduct(string $productId): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setId($productId);

        return $product;
    }

    private function getSalesChannelContext(): SalesChannelContext
    {
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId('salesChannelId');

        return Generator::generateSalesChannelContext(
            salesChannel: $salesChannelEntity,
        );
    }

}

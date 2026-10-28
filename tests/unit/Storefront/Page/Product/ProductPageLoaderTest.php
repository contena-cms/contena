<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Page\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Contena\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Contena\Core\Content\Product\Aggregate\ProductReview\ProductReviewEntity;
use Contena\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\SalesChannel\SalesChannelEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Generator;
use Contena\Storefront\Page\Product\ProductPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductPageLoader::class)]
class ProductPageLoaderTest extends TestCase
{
    public function testItLoadsReviews(): void
    {
        $productId = Uuid::randomHex();
        $request = new Request([], [], ['productId' => $productId]);
        $salesChannelContext = $this->getSalesChannelContext();
        $reviews = $this->getCmsSlotConfig();

        $productPageLoader = $this->getProductPageLoaderWithProduct($productId, $reviews);

        $page = $productPageLoader->load($request, $salesChannelContext);

        $slot = $page->getCmsPage()?->getSections()?->first()?->getBlocks()?->first()?->getSlots()?->first()?->getSlot();
        static::assertIsString($slot);

        static::assertSame($reviews, json_decode($slot, true, 512, \JSON_THROW_ON_ERROR));
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the BREADCRUMB_REWORK flag
     */
    #[DisabledFeatures(['BREADCRUMB_REWORK'])]
    public function testExplicitBreadcrumbOptOutWinsWithMajorActive(): void
    {
        $productId = Uuid::randomHex();
        $request = new Request([], [], ['productId' => $productId]);
        $context = $this->getSalesChannelContext();
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());

        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->expects($this->never())->method('getCategoryBreadcrumbUrls');

        $page = $this->getProductPageLoaderWithProduct(
            $productId,
            $this->getCmsSlotConfig(),
            seoCategory: $category,
            breadcrumbBuilder: $breadcrumbBuilder,
        )->load($request, $context);

        static::assertNull($page->getBreadcrumb());
    }

    public function testItLoadsStructuredDataReviewsForJsonLd(): void
    {
        $productId = Uuid::randomHex();
        $request = new Request([], [], ['productId' => $productId]);
        $salesChannelContext = $this->getSalesChannelContext();

        $review = new ProductReviewEntity();
        $review->setId(Uuid::randomHex());
        $review->setTitle('Great product');
        $review->setContent('Really changed my life');
        $review->setPoints(5);
        $review->setStatus(true);

        $reviewCollection = new ProductReviewCollection([$review]);

        $entityResult = new EntitySearchResult(
            ProductReviewDefinition::ENTITY_NAME,
            1,
            $reviewCollection,
            new AggregationResultCollection([
                new TermsResult('ratingMatrix', []),
            ]),
            new Criteria(),
            Context::createDefaultContext()
        );

        $reviewRepositoryMock = $this->createMock(EntityRepository::class);
        $reviewRepositoryMock
            ->expects(Feature::isActive('JSON_LD_DATA') ? $this->once() : $this->never())
            ->method('search')
            ->willReturn($entityResult);

        $productPageLoader = $this->getProductPageLoaderWithProduct(
            $productId,
            $this->getCmsSlotConfig(),
            $reviewRepositoryMock
        );

        $page = $productPageLoader->load($request, $salesChannelContext);

        if (Feature::isActive('JSON_LD_DATA')) {
            $reviewData = $page->getStructuredDataReviews();
            static::assertNotNull($reviewData);
            static::assertSame(1, $reviewData->getTotal());
            static::assertSame($productId, $reviewData->getProductId());

            $loadedReview = $reviewData->getEntities()->first();
            static::assertNotNull($loadedReview);
            static::assertSame('Great product', $loadedReview->getTitle());
        } else {
            static::assertNull($page->getStructuredDataReviews());
        }
    }

    public function testItSkipsStructuredDataReviewsWhenReviewsAreDisabled(): void
    {
        $productId = Uuid::randomHex();
        $request = new Request([], [], ['productId' => $productId]);
        $salesChannelContext = $this->getSalesChannelContext();

        $reviewRepositoryMock = $this->createMock(EntityRepository::class);
        $reviewRepositoryMock->expects($this->never())->method('search');

        $systemConfigMock = static::createStub(SystemConfigService::class);
        $systemConfigMock->method('getBool')->willReturn(false);

        $productPageLoader = $this->getProductPageLoaderWithProduct(
            $productId,
            $this->getCmsSlotConfig(),
            $reviewRepositoryMock,
            $systemConfigMock,
        );

        $page = $productPageLoader->load($request, $salesChannelContext);

        static::assertNull($page->getStructuredDataReviews());
    }

    public function testItSetsEmptyStructuredDataReviewsWhenNoReviewsExist(): void
    {
        $productId = Uuid::randomHex();
        $request = new Request([], [], ['productId' => $productId]);
        $salesChannelContext = $this->getSalesChannelContext();

        $entityResult = new EntitySearchResult(
            ProductReviewDefinition::ENTITY_NAME,
            0,
            new AggregationResultCollection([
                new TermsResult('ratingMatrix', []),
            ]),
            new Criteria(),
            Context::createDefaultContext()
        );

        $reviewRepositoryMock = $this->createMock(EntityRepository::class);
        $reviewRepositoryMock
            ->expects(Feature::isActive('JSON_LD_DATA') ? $this->once() : $this->never())
            ->method('search')
            ->willReturn($entityResult);

        $productPageLoader = $this->getProductPageLoaderWithProduct(
            $productId,
            $this->getCmsSlotConfig(),
            $reviewRepositoryMock
        );

        $page = $productPageLoader->load($request, $salesChannelContext);

        if (Feature::isActive('JSON_LD_DATA')) {
            $reviewData = $page->getStructuredDataReviews();
            static::assertNotNull($reviewData);
            static::assertSame(0, $reviewData->getTotal());
            static::assertSame(0, $reviewData->getMatrix()->getTotalReviewCount());
        } else {
            static::assertNull($page->getStructuredDataReviews());
        }
    }


    /**
     * @param array<string, array<string, array<string, array<string, array<string, string>>>>> $reviews
     */
    private function getProductWithReviews(string $productId, array $reviews): SalesChannelProductEntity
    {
        $product = new SalesChannelProductEntity();
        $product->setId($productId);

        // set reviews
        $product->setTranslated($reviews);

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


    /**
     * @return array<string, array<string, array<string, array<string, array<string, string>>>>>
     */
    private function getCmsSlotConfig(): array
    {
        return [
            'data' => [
                'reviews' => [
                    'elements' => [
                        'myReviewElement' => [
                            'title' => 'myReviewTitle',
                            'content' => 'this product changed my life',
                        ],
                    ],
                ],
            ],
        ];
    }



}

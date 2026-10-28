<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\SalesChannel\Suggest;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\Extension\ProductSuggestRouteExtension;
use Contena\Core\Content\Product\ProductCollection;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Content\Product\ProductException;
use Contena\Core\Content\Product\SalesChannel\Listing\Processor\CompositeListingProcessor;
use Contena\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Contena\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Contena\Core\Content\Product\SalesChannel\Suggest\AbstractProductSuggestRoute;
use Contena\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRoute;
use Contena\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRouteResponse;
use Contena\Core\Content\Product\SalesChannel\Suggest\ResolvedCriteriaProductSuggestRoute;
use Contena\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductSuggestRoute::class)]
class ProductSuggestRouteTest extends TestCase
{
    /**
     * @var ProductListingLoader&MockObject
     */
    private ProductListingLoader $listingLoader;

    protected function setUp(): void
    {
        $this->listingLoader = $this->createMock(ProductListingLoader::class);
    }

    public function testGetDecoratedShouldThrowException(): void
    {
        $this->listingLoader->expects($this->never())->method('load');

        $this->expectExceptionObject(new DecorationPatternException(ProductSuggestRoute::class));

        $this->getProductSuggestRoute()->getDecorated();
    }

    public function testLoadThrowsExceptionForMissingSearchParameter(): void
    {
        $this->listingLoader->expects($this->never())->method('load');

        $this->expectExceptionObject(ProductException::missingRequestParameter('search'));

        $route = new ResolvedCriteriaProductSuggestRoute(
            static::createStub(ProductSearchBuilderInterface::class),
            new EventDispatcher(),
            static::createStub(AbstractProductSuggestRoute::class),
            new CompositeListingProcessor([])
        );

        $route->load(
            new Request(),
            static::createStub(SalesChannelContext::class),
            new Criteria()
        );
    }

    public function testLoadSuccessfully(): void
    {
        $request = new Request();
        $request->query->set('search', 'test');

        $criteria = new Criteria();

        $this->listingLoader->expects($this->once())
            ->method('load')
            ->willReturn(new ProductListingResult(
                ProductDefinition::ENTITY_NAME,
                1,
                new ProductCollection([]),
                null,
                $criteria,
                Context::createDefaultContext()
            ));

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $this->getProductSuggestRoute()->load(
            $request,
            $salesChannelContext,
            $criteria
        );
    }

    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();
        $response = static::createStub(ProductSuggestRouteResponse::class);

        $this->listingLoader->expects($this->never())->method('load');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('product-suggest-route.load.pre', static function (ProductSuggestRouteExtension $extension) use ($request, $context, $criteria, $response): void {
            static::assertSame(['request' => $request, 'context' => $context, 'criteria' => $criteria], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ProductSuggestRoute(
            $this->listingLoader,
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $context, $criteria));
    }

    private function getProductSuggestRoute(): ProductSuggestRoute
    {
        return new ProductSuggestRoute(
            $this->listingLoader,
            new ExtensionDispatcher(new EventDispatcher())
        );
    }
}

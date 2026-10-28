<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Storefront\Page\Product\QuickView;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\Exception\ProductNotFoundException;
use Contena\Core\Content\Product\ProductException;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Storefront\Page\Product\QuickView\MinimalQuickViewPageCriteriaEvent;
use Contena\Storefront\Page\Product\QuickView\MinimalQuickViewPageLoadedEvent;
use Contena\Storefront\Page\Product\QuickView\MinimalQuickViewPageLoader;
use Contena\Storefront\Test\Page\StorefrontPageTestBehaviour;
use Contena\Tests\Integration\Storefront\Page\StorefrontPageTestConstants;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
class MinimalQuickViewPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItRequiresProductParam(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContext();

        $this->expectParamMissingException('productId');
        $this->getPageLoader()->load($request, $context);
    }

    public function testItRequiresAValidProductParam(): void
    {
        $request = new Request([], [], ['productId' => '99999911ffff4fffafffffff19830531']);
        $context = $this->createSalesChannelContext();

        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(ProductNotFoundException::class);
        } else {
            $this->expectException(ProductException::class);
        }

        $this->getPageLoader()->load($request, $context);
    }

    public function testItFailsWithANonExistingProduct(): void
    {
        $context = $this->createSalesChannelContext();
        $request = new Request([], [], ['productId' => Uuid::randomHex()]);

        $event = null;
        $this->catchEvent(MinimalQuickViewPageLoadedEvent::class, $event);

        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(ProductNotFoundException::class);
        } else {
            $this->expectException(ProductException::class);
        }

        $this->getPageLoader()->load($request, $context);
    }

    public function testItDoesLoadATestProduct(): void
    {
        $context = $this->createSalesChannelContext();
        $product = $this->getRandomProduct($context);

        $request = new Request([], [], ['productId' => $product->getId()]);

        $event = null;
        $this->catchEvent(MinimalQuickViewPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame(StorefrontPageTestConstants::PRODUCT_NAME, $page->getProduct()->getName());
        self::assertPageEvent(MinimalQuickViewPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testItDispatchPageCriteriaEvent(): void
    {
        $context = $this->createSalesChannelContextWithNavigation();
        $product = $this->getRandomProduct($context);

        $request = new Request([], [], ['productId' => $product->getId()]);

        $event = null;
        $this->catchEvent(MinimalQuickViewPageCriteriaEvent::class, $event);

        $this->getPageLoader()->load($request, $context);
        static::assertInstanceOf(MinimalQuickViewPageCriteriaEvent::class, $event);
    }

    protected function getPageLoader(): MinimalQuickViewPageLoader
    {
        return static::getContainer()->get(MinimalQuickViewPageLoader::class);
    }
}

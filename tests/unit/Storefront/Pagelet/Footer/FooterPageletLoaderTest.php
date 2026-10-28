<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Pagelet\Footer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Payment\PaymentMethodCollection;
use Contena\Core\Checkout\Payment\PaymentMethodDefinition;
use Contena\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Contena\Core\Checkout\Payment\SalesChannel\PaymentMethodRouteResponse;
use Contena\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Contena\Core\Checkout\Shipping\SalesChannel\ShippingMethodRouteResponse;
use Contena\Core\Checkout\Shipping\ShippingMethodCollection;
use Contena\Core\Checkout\Shipping\ShippingMethodDefinition;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Service\NavigationLoaderInterface;
use Contena\Core\Content\Category\Tree\Tree;
use Contena\Core\Content\Category\Tree\TreeItem;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Generator;
use Contena\Storefront\Pagelet\Footer\FooterPageletLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(FooterPageletLoader::class)]
class FooterPageletLoaderTest extends TestCase
{
    public function testLoad(): void
    {
        $serviceMenuId = Uuid::randomHex();
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->getSalesChannel()->setServiceCategoryId($serviceMenuId);

        $eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $navigationLoader = static::createStub(NavigationLoaderInterface::class);

        $categoryId1 = Uuid::randomHex();
        $categoryId2 = Uuid::randomHex();
        $category1 = (new CategoryEntity())->assign(['id' => $categoryId1]);
        $category2 = (new CategoryEntity())->assign(['id' => $categoryId2]);
        $navigationLoader->method('load')->willReturnMap(
            [
                [
                    $serviceMenuId,
                    $salesChannelContext,
                    $serviceMenuId,
                    1,
                    new Tree($category2, [new TreeItem($category1, []), new TreeItem($category2, [])]),
                ],
            ]
        );

        $paymentMethodCollection = new PaymentMethodCollection();
        $paymentMethodRoute = static::createStub(AbstractPaymentMethodRoute::class);
        $paymentMethodRoute->method('load')->willReturn(new PaymentMethodRouteResponse(
            new EntitySearchResult(
                PaymentMethodDefinition::ENTITY_NAME,
                0,
                $paymentMethodCollection,
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            )
        ));

        $shippingMethodCollection = new ShippingMethodCollection();
        $shippingMethodRoute = static::createStub(AbstractShippingMethodRoute::class);
        $shippingMethodRoute->method('load')->willReturn(new ShippingMethodRouteResponse(
            new EntitySearchResult(
                ShippingMethodDefinition::ENTITY_NAME,
                0,
                $shippingMethodCollection,
                null,
                new Criteria(),
                $salesChannelContext->getContext()
            )
        ));

        $footerPageletLoader = new FooterPageletLoader($eventDispatcher, $navigationLoader, $paymentMethodRoute, $shippingMethodRoute);
        $footer = $footerPageletLoader->load(new Request(), $salesChannelContext);

        $serviceMenu = $footer->getServiceMenu();
        static::assertCount(2, $serviceMenu);
        static::assertSame($category1, $serviceMenu->get($categoryId1));
        static::assertSame($category2, $serviceMenu->get($categoryId2));

        static::assertSame($paymentMethodCollection, $footer->getPaymentMethods());
        static::assertSame($shippingMethodCollection, $footer->getShippingMethods());
    }
}

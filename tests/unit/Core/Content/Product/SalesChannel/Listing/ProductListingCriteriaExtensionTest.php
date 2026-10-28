<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\SalesChannel\Listing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\Extension\ProductListingCriteriaExtension;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductListingCriteriaExtension::class)]
class ProductListingCriteriaExtensionTest extends TestCase
{
    public function testProductListingCriteriaExample(): void
    {
        $example = new ProductListingCriteriaExtensionExample();

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($example);

        $extension = new ProductListingCriteriaExtension(
            new Criteria(),
            static::createStub(SalesChannelContext::class),
            'categoryId'
        );

        $result = (new ExtensionDispatcher($dispatcher))->publish(
            name: ProductListingCriteriaExtension::NAME,
            extension: $extension,
            function: static function (Criteria $criteria, SalesChannelContext $context, string $categoryId): Criteria {
                $criteria->addFilter(
                    new EqualsFilter('product.categoriesRo.id', $categoryId)
                );

                return $criteria;
            }
        );

        static::assertInstanceOf(Criteria::class, $result);
        static::assertSame([], $result->getFilters());
    }
}

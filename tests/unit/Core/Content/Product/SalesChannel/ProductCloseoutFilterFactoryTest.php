<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\SalesChannel\ProductCloseoutFilter;
use Contena\Core\Content\Product\SalesChannel\ProductCloseoutFilterFactory;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductCloseoutFilterFactory::class)]
class ProductCloseoutFilterFactoryTest extends TestCase
{
    public function testCreatesProductCloseoutFilter(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $filter = (new ProductCloseoutFilterFactory())->create($context);

        static::assertEquals(new ProductCloseoutFilter(), $filter);
    }

    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        static::expectException(DecorationPatternException::class);
        (new ProductCloseoutFilterFactory())->getDecorated();
    }
}

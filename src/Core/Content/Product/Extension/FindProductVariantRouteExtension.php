<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Extension;

use Contena\Core\Content\Product\SalesChannel\FindVariant\FindProductVariantRouteResponse;
use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<FindProductVariantRouteResponse>
 */
#[Package('inventory')]
final class FindProductVariantRouteExtension extends Extension
{
    public const NAME = 'find-product-variant-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $productId,
        public readonly Request $request,
        public readonly SalesChannelContext $context,
    ) {
    }
}

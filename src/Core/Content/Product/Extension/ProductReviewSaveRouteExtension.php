<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Extension;

use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\SalesChannel\NoContentResponse;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<NoContentResponse>
 */
#[Package('after-sales')]
final class ProductReviewSaveRouteExtension extends Extension
{
    public const NAME = 'product-review-save-route.save';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly string $productId,
        public readonly RequestDataBag $data,
        public readonly SalesChannelContext $context,
    ) {
    }
}
